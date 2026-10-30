<?php

namespace Tests\Feature\Fiolux;

use App\Actions\Fiolux\RecordMovementAction;
use App\Actions\Fiolux\RecordTechnicalEvaluationAction;
use App\Models\Actionlog;
use App\Models\FioluxCycle;
use App\Models\FioluxDocument;
use App\Models\FioluxInspection;
use App\Models\FioluxNotebook;
use App\Models\User;
use App\Services\Fiolux\Documents;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use Tests\TestCase;

class NotebookFlowTest extends TestCase
{
    protected function setUp(): void
    {
        if (! filter_var(getenv('FIOLUX_ENABLED'), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('Suíte opcional Fiolux. Use Test-Proof.ps1 para habilitar no banco isolado.');
        }
        if (getenv('DB_DATABASE') !== 'teste_fiolux_tests') {
            throw new \RuntimeException('Suíte Fiolux exige DB_DATABASE=teste_fiolux_tests antes de inicializar o banco. Use Test-Proof.ps1.');
        }
        parent::setUp();
    }

    public function test_missing_confirmation_returns_error_without_movement(): void
    {
        [$notebook, $ti, $rh, $a] = $this->fixture();
        $payload = $this->payload('delivery', $a->id);
        unset($payload['confirmed']);

        $this->actingAs($ti)->postJson(route('fiolux.store', $notebook->asset_id), $payload)
            ->assertOk()->assertJsonPath('status', 'error')->assertJsonValidationErrors('confirmed', 'messages');

        $this->assertDatabaseCount('fiolux_cycles', 0);
        $this->assertNull($notebook->asset->fresh()->assigned_to);
        $this->assertDatabaseMissing('action_logs', ['item_id' => $notebook->asset_id, 'action_type' => 'checkout']);
    }

    public function test_duplicate_delivery_keeps_one_cycle_and_one_native_movement(): void
    {
        [$notebook, $ti, $rh, $a] = $this->fixture();
        $payload = $this->payload('delivery', $a->id);
        $this->actingAs($ti);
        $first = RecordMovementAction::run($notebook->asset, $ti, $payload);

        $repeat = RecordMovementAction::run($notebook->asset, $ti, $payload);

        $this->assertSame($first->id, $repeat->id);
        $this->assertDatabaseCount('fiolux_cycles', 1);
        $this->assertSame(1, Actionlog::where('item_id', $notebook->asset_id)->where('action_type', 'checkout')->count());
        $this->assertDatabaseHas('action_logs', ['id' => $first->actionlog_id, 'item_id' => $notebook->asset_id, 'target_id' => $a->id, 'action_type' => 'checkout']);
    }

    public function test_duplicate_key_with_changed_answers_returns_error(): void
    {
        [$notebook, $ti, $rh, $a] = $this->fixture();
        $payload = $this->payload('delivery', $a->id);
        $this->actingAs($ti)->postJson(route('fiolux.store', $notebook->asset_id), $payload)->assertRedirect();
        $payload['answers']['screen'] = 'avaria';

        $this->postJson(route('fiolux.store', $notebook->asset_id), $payload)->assertOk()->assertJsonPath('status', 'error');

        $this->assertDatabaseCount('fiolux_inspections', 1);
    }

    public function test_return_is_recorded_by_rh_and_keeps_asset_unavailable(): void
    {
        [$notebook, $ti, $rh, $a] = $this->fixture();
        $delivery = $this->deliver($notebook, $ti, $a);
        $this->actingAs($rh);

        $returned = RecordMovementAction::run($notebook->asset, $rh, $this->payload('return', null, $delivery->cycle_id));

        $this->assertSame($rh->id, $returned->operator_id);
        $this->assertNotSame($delivery->operator_id, $returned->operator_id);
        $this->assertNull($notebook->asset->fresh()->assigned_to);
        $this->assertSame((int) $notebook->pending_status_id, (int) $notebook->asset->fresh()->status_id);
        $this->assertFalse($notebook->asset->fresh()->availableForCheckout());
        $this->postJson(route('fiolux.technical', $delivery->cycle_id), $this->technicalPayload())->assertForbidden();
        $this->assertDatabaseCount('fiolux_technical_evaluations', 0);
    }

    public function test_second_return_is_rejected_without_duplicate_history(): void
    {
        [$notebook, $ti, $rh, $a] = $this->fixture();
        $delivery = $this->deliver($notebook, $ti, $a);
        $payload = $this->payload('return', null, $delivery->cycle_id);
        $this->actingAs($rh)->postJson(route('fiolux.store', $notebook->asset_id), $payload)->assertRedirect();
        $payload['operation_key'] = (string) Str::uuid();

        $this->postJson(route('fiolux.store', $notebook->asset_id), $payload)->assertOk()->assertJsonPath('status', 'error');

        $this->assertDatabaseCount('fiolux_inspections', 2);
        $this->assertSame(1, Actionlog::where('item_id', $notebook->asset_id)->where('action_type', 'checkin from')->count());
    }

    public function test_signature_pending_blocks_new_delivery_even_after_technical_release(): void
    {
        [$notebook, $ti, $rh, $a, $b] = $this->fixture();
        $delivery = $this->deliver($notebook, $ti, $a);
        $this->actingAs($rh);
        RecordMovementAction::run($notebook->asset, $rh, $this->payload('return', null, $delivery->cycle_id));
        $this->actingAs($ti);
        RecordTechnicalEvaluationAction::run($delivery->cycle, $ti, $this->technicalPayload());

        $this->postJson(route('fiolux.store', $notebook->asset_id), $this->payload('delivery', $b->id))->assertOk()->assertJsonPath('status', 'error');

        $this->assertDatabaseCount('fiolux_cycles', 1);
        $this->assertNull($notebook->asset->fresh()->assigned_to);
        $this->assertSame($ti->id, $delivery->cycle->evaluations()->first()->operator_id);
    }

    public function test_two_cycles_preserve_all_answers_even_when_unchanged_and_keep_documents(): void
    {
        [$notebook, $ti, $rh, $a, $b] = $this->fixture();
        Storage::fake('local');
        $this->travelTo(now()->startOfDay());
        $deliveryA = $this->deliver($notebook, $ti, $a);
        $original = $deliveryA->snapshot;
        $documentA = $this->signed($deliveryA, $ti);
        $bytesA = Storage::get($documentA->path);
        $this->actingAs($rh);
        $changed = $this->payload('return', null, $deliveryA->cycle_id);
        $changed['answers']['screen'] = 'avaria';
        $changed['answers']['charger'] = 'ausente';
        $this->travel(1)->hours();
        $returnA = RecordMovementAction::run($notebook->asset, $rh, $changed);
        $this->signed($returnA, $rh);
        $this->actingAs($ti);
        $this->travel(1)->hours();
        RecordTechnicalEvaluationAction::run($deliveryA->cycle, $ti, $this->technicalPayload());
        $next = $this->payload('delivery', $b->id);
        $next['answers'] = $changed['answers'];
        $deliveryB = RecordMovementAction::run($notebook->asset, $ti, $next);
        $this->signed($deliveryB, $ti);
        $this->actingAs($rh);
        $last = $this->payload('return', null, $deliveryB->cycle_id);
        $last['answers'] = $next['answers'];

        $returnB = RecordMovementAction::run($notebook->asset, $rh, $last);

        $this->assertNotSame($deliveryA->cycle_id, $deliveryB->cycle_id);
        $this->assertSame($original, $deliveryA->fresh()->snapshot);
        $this->assertSame($deliveryB->snapshot['questions'], $returnB->snapshot['questions']);
        $this->assertCount(4, $returnB->snapshot['questions']);
        $this->assertSame('avaria', $returnA->snapshot['questions']['screen']['answer']);
        $this->assertSame('ausente', $returnA->snapshot['questions']['charger']['answer']);
        $this->assertSame($bytesA, Storage::get($documentA->path));
        $this->assertDatabaseCount('fiolux_cycles', 2);
        $this->assertDatabaseCount('fiolux_inspections', 4);
        $this->actingAs($ti)->get(route('fiolux.show', $deliveryA->cycle_id))->assertSee(trans('fiolux.different'))->assertSee(trans('fiolux.avaria'));
    }

    public function test_completed_inspection_cannot_be_edited_or_deleted(): void
    {
        [$notebook, $ti, $rh, $a] = $this->fixture();
        $inspection = $this->deliver($notebook, $ti, $a);
        $original = $inspection->snapshot;

        $this->rejects(fn () => $inspection->update(['snapshot' => ['changed' => true]]));
        $this->rejects(fn () => $inspection->fresh()->delete());

        $this->assertSame($original, $inspection->fresh()->snapshot);
    }

    public function test_model_version_and_asset_changes_do_not_rewrite_previous_snapshot(): void
    {
        [$notebook, $ti, $rh, $a] = $this->fixture();
        $inspection = $this->deliver($notebook, $ti, $a);
        $original = $inspection->snapshot;
        $notebook->asset->update(['serial' => 'TESTE FIOLUX SERIAL ALTERADO']);
        config(['fiolux.template_version' => 'notebook-2']);
        \Illuminate\Support\Facades\Lang::addLines(['fiolux.screen' => 'TESTE FIOLUX Tela modelo 2'], app()->getLocale());
        $this->actingAs($rh);

        $returned = RecordMovementAction::run($notebook->asset, $rh, $this->payload('return', null, $inspection->cycle_id));

        $this->assertSame('notebook-1', $inspection->fresh()->template_version);
        $this->assertSame($original, $inspection->fresh()->snapshot);
        $this->assertSame('notebook-2', $returned->template_version);
        $this->assertSame('TESTE FIOLUX Tela modelo 2', $returned->snapshot['questions']['screen']['label']);
        $this->assertSame('TESTE FIOLUX SERIAL ALTERADO', $returned->snapshot['asset']['serial']);
    }

    public function test_pdf_failure_and_retry_do_not_repeat_movement(): void
    {
        [$notebook, $ti, $rh, $a] = $this->fixture();
        Storage::fake('local');
        $inspection = $this->deliver($notebook, $ti, $a);
        $failing = $this->partialMock(Documents::class, function (MockInterface $mock): void {
            $mock->shouldReceive('renderPdf')->once()->andThrow(new \RuntimeException('TESTE FIOLUX PDF failure'));
        });

        $failed = $failing->generate($inspection, $ti);
        $ready = (new Documents)->generate($inspection, $ti);

        $this->assertSame('failed', $failed->state);
        $this->assertNull($failed->path);
        $this->assertSame('ready', $ready->state);
        Storage::assertExists($ready->path);
        $this->assertStringStartsWith('%PDF-', Storage::get($ready->path));
        $this->assertDatabaseCount('fiolux_inspections', 1);
        $this->assertSame(1, Actionlog::where('item_id', $notebook->asset_id)->where('action_type', 'checkout')->count());
    }

    public function test_upload_failure_keeps_movement_and_records_failed_attempt(): void
    {
        [$notebook, $ti, $rh, $a] = $this->fixture();
        $inspection = $this->deliver($notebook, $ti, $a);
        Storage::shouldReceive('put')->once()->andReturn(false);
        $file = UploadedFile::fake()->createWithContent('TESTE-FIOLUX.pdf', "%PDF-1.4\nTESTE FIOLUX\n%%EOF");

        $document = (new Documents)->upload($inspection, $ti, $file);

        $this->assertSame('failed', $document->state);
        $this->assertNull($document->path);
        $this->assertDatabaseCount('fiolux_inspections', 1);
        $this->assertSame($a->id, $notebook->asset->fresh()->assigned_to);
    }

    public function test_upload_rejects_active_content(): void
    {
        [$notebook, $ti, $rh, $a] = $this->fixture();
        $inspection = $this->deliver($notebook, $ti, $a);

        $this->postJson(route('fiolux.upload', $inspection), ['file' => UploadedFile::fake()->createWithContent('TESTE-FIOLUX.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')])->assertOk()->assertJsonPath('status', 'error');

        $this->assertDatabaseCount('fiolux_documents', 0);
    }

    public function test_rh_cannot_deliver_and_management_cannot_upload(): void
    {
        [$notebook, $ti, $rh, $a, $b, $management] = $this->fixture();
        $this->actingAs($rh)->postJson(route('fiolux.store', $notebook->asset_id), $this->payload('delivery', $a->id))->assertForbidden();
        $this->assertDatabaseCount('fiolux_cycles', 0);
        $inspection = $this->deliver($notebook, $ti, $a);

        $this->actingAs($management)->postJson(route('fiolux.upload', $inspection), ['file' => UploadedFile::fake()->createWithContent('TESTE-FIOLUX.pdf', "%PDF-1.4\nTESTE FIOLUX")])->assertForbidden();

        $this->assertDatabaseCount('fiolux_documents', 0);
    }

    public function test_pages_render_and_new_form_does_not_reuse_asset_answers(): void
    {
        [$notebook, $ti, $rh, $a] = $this->fixture();
        $this->actingAs($ti)->get(route('fiolux.index'))->assertSee($notebook->asset->asset_tag);
        $this->get(route('fiolux.create', $notebook->asset_id))->assertSee(trans('fiolux.confirm'))->assertDontSee('value="conforme" selected', false);
        $inspection = $this->deliver($notebook, $ti, $a);
        $this->get(route('fiolux.show', $inspection->cycle_id))->assertSee(trans('fiolux.signature_pending'));
        $this->actingAs($rh)->get(route('fiolux.create', ['asset' => $notebook->asset_id, 'kind' => 'return']))->assertSee(trans('fiolux.original'))->assertDontSee('value="conforme" selected', false);
    }

    public function test_unauthorized_pages_and_direct_download_are_forbidden(): void
    {
        [$notebook, $ti, $rh, $a] = $this->fixture();
        Storage::fake('local');
        $inspection = $this->deliver($notebook, $ti, $a);
        $document = (new Documents)->generate($inspection, $ti);
        $this->actingAs($a);

        $this->get(route('fiolux.index'))->assertForbidden();
        $this->get(route('fiolux.create', $notebook->asset_id))->assertForbidden();
        $this->get(route('fiolux.show', $inspection->cycle_id))->assertForbidden();
        $this->get(route('fiolux.download', $document))->assertForbidden();
        $this->actingAs($rh)->get(route('fiolux.download', $document))->assertDownload();
    }

    public function test_native_web_and_api_checkout_cannot_bypass_conference(): void
    {
        [$notebook, $ti, $rh, $a] = $this->fixture();
        $this->actingAs($ti)->postJson(route('hardware.checkout.store', $notebook->asset_id), ['assigned_user' => $a->id, 'checkout_to_type' => 'user'])->assertOk()->assertJsonPath('status', 'error');

        $this->actingAsForApi($ti)->postJson('/api/v1/hardware/'.$notebook->asset_id.'/checkout', ['assigned_user' => $a->id, 'checkout_to_type' => 'user'])->assertOk()->assertJsonPath('status', 'error');

        $this->assertNull($notebook->asset->fresh()->assigned_to);
        $this->assertDatabaseCount('fiolux_inspections', 0);
    }

    public function test_native_checkin_and_status_edit_cannot_bypass_conference(): void
    {
        [$notebook, $ti, $rh, $a] = $this->fixture();
        $inspection = $this->deliver($notebook, $ti, $a);

        $this->postJson(route('hardware.checkin.store', $notebook->asset_id))->assertOk()->assertJsonPath('status', 'error');
        $this->rejects(fn () => $notebook->asset->update(['status_id' => $notebook->pending_status_id]));

        $this->assertSame($a->id, $notebook->asset->fresh()->assigned_to);
        $this->assertDatabaseCount('fiolux_inspections', 1);
    }

    public function test_wrong_asset_cycle_is_rejected_without_moving_either_asset(): void
    {
        [$notebook, $ti, $rh, $a] = $this->fixture();
        $inspection = $this->deliver($notebook, $ti, $a);
        $other = FioluxNotebook::factory()->create();

        $this->actingAs($rh)->postJson(route('fiolux.store', $other->asset_id), $this->payload('return', null, $inspection->cycle_id))->assertOk()->assertJsonPath('status', 'error');

        $this->assertSame($a->id, $notebook->asset->fresh()->assigned_to);
        $this->assertNull($other->asset->fresh()->assigned_to);
        $this->assertDatabaseCount('fiolux_inspections', 1);
    }

    public function test_upload_without_document_review_does_not_remove_delivery_block(): void
    {
        [$notebook, $ti, $rh, $a, $b] = $this->fixture();
        Storage::fake('local');
        $delivery = $this->deliver($notebook, $ti, $a);
        $this->actingAs($rh);
        $returned = RecordMovementAction::run($notebook->asset, $rh, $this->payload('return', null, $delivery->cycle_id));
        $this->actingAs($ti);
        foreach ([$delivery, $returned] as $inspection) {
            (new Documents)->upload($inspection, $ti, UploadedFile::fake()->createWithContent('TESTE-FIOLUX.pdf', "%PDF-1.4\nTESTE FIOLUX\n%%EOF"));
        }
        RecordTechnicalEvaluationAction::run($delivery->cycle, $ti, $this->technicalPayload());

        $this->postJson(route('fiolux.store', $notebook->asset_id), $this->payload('delivery', $b->id))->assertJsonPath('status', 'error');

        $this->assertDatabaseCount('fiolux_cycles', 1);
        $this->assertNull($notebook->asset->fresh()->assigned_to);
    }

    public function test_missing_signed_file_reinstates_block_even_after_document_review(): void
    {
        [$notebook, $ti, $rh, $a, $b] = $this->fixture();
        Storage::fake('local');
        $delivery = $this->deliver($notebook, $ti, $a);
        $document = $this->signed($delivery, $ti);
        $this->actingAs($rh);
        $returned = RecordMovementAction::run($notebook->asset, $rh, $this->payload('return', null, $delivery->cycle_id));
        $this->signed($returned, $rh);
        $this->actingAs($ti);
        RecordTechnicalEvaluationAction::run($delivery->cycle, $ti, $this->technicalPayload());
        Storage::delete($document->path);

        $this->postJson(route('fiolux.store', $notebook->asset_id), $this->payload('delivery', $b->id))->assertJsonPath('status', 'error');

        $this->assertDatabaseCount('fiolux_cycles', 1);
        $this->assertNull($notebook->asset->fresh()->assigned_to);
    }

    public function test_permissions_distinguish_ti_rh_management_and_employee(): void
    {
        [$notebook, $ti, $rh, $a, $b, $management] = $this->fixture();
        $inspection = $this->deliver($notebook, $ti, $a);

        foreach ([[$ti, [true, true, true, true]], [$rh, [false, true, false, true]], [$management, [false, false, false, false]], [$a, [false, false, false, false]]] as [$actor, $expected]) {
            $actual = [
                Gate::forUser($actor)->allows('checkout', FioluxCycle::class),
                Gate::forUser($actor)->allows('checkin', $inspection->cycle),
                Gate::forUser($actor)->allows('technical', $inspection->cycle),
                Gate::forUser($actor)->allows('documents', $inspection->cycle),
            ];
            $this->assertSame($expected, $actual);
        }
        $this->assertFalse($rh->isSuperUser());
        $this->assertFalse($rh->hasAccess('admin'));
    }

    public function test_notes_are_escaped_in_comparison_and_pdf_html(): void
    {
        [$notebook, $ti, $rh, $a] = $this->fixture();
        $payload = $this->payload('delivery', $a->id);
        $payload['notes'] = '<script>TESTE FIOLUX</script>';
        $inspection = RecordMovementAction::run($notebook->asset, $ti, $payload);

        $this->get(route('fiolux.show', $inspection->cycle_id))->assertSee('&lt;script&gt;TESTE FIOLUX&lt;/script&gt;', false)->assertDontSee('<script>TESTE FIOLUX</script>', false);
        $html = view('fiolux.pdf', compact('inspection'))->render();

        $this->assertStringContainsString('&lt;script&gt;TESTE FIOLUX&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>TESTE FIOLUX</script>', $html);
    }

    public function test_unmanaged_asset_keeps_native_checkout_behavior(): void
    {
        [$notebook, $ti, $rh, $a] = $this->fixture();
        $other = $notebook->asset->replicate();
        $other->asset_tag = 'TESTE FIOLUX SEM CHECKLIST';
        $other->save();

        $this->assertTrue($other->checkOut($a, $ti, now()->format('Y-m-d H:i:s')));

        $this->assertSame($a->id, $other->fresh()->assigned_to);
        $this->assertDatabaseCount('fiolux_inspections', 0);
    }

    public function test_signed_upload_preserves_versions_and_requires_explicit_document_review(): void
    {
        [$notebook, $ti, $rh, $a] = $this->fixture();
        Storage::fake('local');
        $inspection = $this->deliver($notebook, $ti, $a);
        $this->post(route('fiolux.upload', $inspection), ['file' => UploadedFile::fake()->createWithContent('TESTE-FIOLUX.pdf', "%PDF-1.4\nTESTE FIOLUX V1\n%%EOF")])->assertRedirect(route('fiolux.show', $inspection->cycle_id));
        $first = FioluxDocument::firstOrFail();
        $original = Storage::get($first->path);

        $this->post(route('fiolux.upload', $inspection), ['file' => UploadedFile::fake()->createWithContent('TESTE-FIOLUX.pdf', "%PDF-1.4\nTESTE FIOLUX V2\n%%EOF")])->assertRedirect();
        $last = FioluxDocument::orderByDesc('id')->firstOrFail();
        $this->postJson(route('fiolux.review', $first), [])->assertJsonPath('status', 'error');
        $this->post(route('fiolux.review', $first), ['confirmed' => 1])->assertRedirect();

        $this->assertNotSame($first->path, $last->path);
        $this->assertSame($original, Storage::get($first->path));
        $this->assertSame($ti->id, $first->fresh()->checked_by);
        $this->assertNull($last->fresh()->checked_at);
        $this->assertDatabaseCount('fiolux_documents', 2);
        $this->delete(route('fiolux.download', $first))->assertMethodNotAllowed();
        Storage::assertExists($first->path);
    }

    private function fixture(): array
    {
        config(['fiolux.template_version' => 'notebook-1']);
        $base = ['assets.view' => '1', 'users.view' => '1', 'fiolux.view' => '1'];
        $users = [];
        foreach ([
            'ti' => $base + ['assets.checkout' => '1', 'assets.checkin' => '1', 'fiolux.checkout' => '1', 'fiolux.checkin' => '1', 'fiolux.technical' => '1', 'fiolux.documents' => '1'],
            'rh' => $base + ['fiolux.checkin' => '1', 'fiolux.documents' => '1'],
            'a' => [], 'b' => [], 'gestao' => $base,
        ] as $role => $permissions) {
            $users[$role] = User::factory()->withoutCompany()->create(['first_name' => 'TESTE FIOLUX', 'last_name' => strtoupper($role), 'locale' => 'pt-BR', 'permissions' => json_encode($permissions), 'email' => null, 'end_date' => null]);
        }
        $this->actingAs($users['ti']);

        return [FioluxNotebook::factory()->create(), $users['ti'], $users['rh'], $users['a'], $users['b'], $users['gestao']];
    }

    private function payload(string $kind, ?int $userId = null, ?int $cycleId = null): array
    {
        return array_filter([
            'kind' => $kind, 'operation_key' => (string) Str::uuid(), 'user_id' => $userId, 'cycle_id' => $cycleId,
            'answers' => ['screen' => 'conforme', 'case' => 'conforme', 'charger' => 'presente', 'cable' => 'presente'],
            'confirmed' => 1, 'notes' => 'TESTE FIOLUX Observação', 'reason' => 'TESTE FIOLUX Motivo',
        ], fn ($value) => $value !== null);
    }

    private function technicalPayload(): array
    {
        return ['operation_key' => (string) Str::uuid(), 'result' => 'available', 'notes' => 'TESTE FIOLUX avaliação técnica', 'confirmed' => 1];
    }

    private function deliver(FioluxNotebook $notebook, User $ti, User $employee): FioluxInspection
    {
        $this->actingAs($ti);

        return RecordMovementAction::run($notebook->asset, $ti, $this->payload('delivery', $employee->id));
    }

    private function signed(FioluxInspection $inspection, User $operator): FioluxDocument
    {
        $documents = new Documents;
        $file = UploadedFile::fake()->createWithContent('TESTE-FIOLUX.pdf', "%PDF-1.4\nTESTE FIOLUX assinatura fictícia\n%%EOF");
        $document = $documents->upload($inspection, $operator, $file);
        $documents->review($document, $operator);

        return $document;
    }

    private function rejects(callable $operation): void
    {
        try {
            $operation();
            $this->fail('A operação deveria ter sido rejeitada.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }
}
