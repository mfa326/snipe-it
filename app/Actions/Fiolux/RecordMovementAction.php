<?php

namespace App\Actions\Fiolux;

use App\Events\CheckoutableCheckedIn;
use App\Http\Requests\Fiolux\ChecklistRequest;
use App\Models\Asset;
use App\Models\FioluxCycle;
use App\Models\FioluxInspection;
use App\Models\FioluxNotebook;
use App\Models\User;
use App\Services\Fiolux\MovementContext;
use App\Services\Fiolux\NotebookChecklist;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RecordMovementAction
{
    public static function run(Asset $asset, User $operator, array $data): FioluxInspection
    {
        abort_unless(config('fiolux.enabled') && auth()->id() === $operator->id, 403);
        $data = Validator::make($data, (new ChecklistRequest)->rules())->validate();
        $hash = hash('sha256', json_encode([
            (int) $asset->id, $data['kind'], (int) ($data['user_id'] ?? 0), (int) ($data['cycle_id'] ?? 0),
            array_map(fn ($key) => $data['answers'][$key], ['screen', 'case', 'charger', 'cable']),
            $data['notes'] ?? '', $data['reason'] ?? '',
        ], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($asset, $operator, $data, $hash): FioluxInspection {
            $locked = Asset::whereKey($asset->id)->lockForUpdate()->firstOrFail();
            $notebook = FioluxNotebook::where('asset_id', $locked->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('view', $locked);
            if ($data['kind'] === 'delivery') {
                Gate::authorize('checkout', FioluxCycle::class);
            } else {
                $cycle = FioluxCycle::whereKey($data['cycle_id'])->lockForUpdate()->firstOrFail();
                Gate::authorize('checkin', $cycle);
                static::ensure((int) $cycle->asset_id === (int) $locked->id, 'wrong_cycle');
            }

            $existing = FioluxInspection::where('operation_key', $data['operation_key'])->first();
            if ($existing) {
                static::ensure($existing->payload_hash === $hash && (int) $existing->operator_id === (int) $operator->id, 'duplicate_conflict');

                return $existing;
            }

            if ($data['kind'] === 'delivery') {
                $previous = $notebook->lastCycle;
                static::ensure($locked->availableForCheckout(), 'unavailable');
                if ($previous) {
                    static::ensure($previous->returned_at !== null, 'unavailable');
                    static::ensure($previous->evaluations()->orderByDesc('id')->first()?->result === 'available', 'technical_pending');
                    static::ensure(! $previous->signaturePending(), 'signature_pending');
                }
                $user = User::findOrFail($data['user_id']);
                Gate::authorize('view', $user);
                static::ensure($locked->canCheckoutTo($user), 'wrong_cycle');
                $cycle = FioluxCycle::create(['asset_id' => $locked->id, 'user_id' => $user->id, 'open_asset_id' => $locked->id]);
            } else {
                static::ensure(! $cycle->returned_at && (int) $notebook->last_cycle_id === (int) $cycle->id, 'already_returned');
                static::ensure((int) $locked->assigned_to === (int) $cycle->user_id && $locked->assigned_type === User::class, 'wrong_cycle');
                $user = $cycle->user;
            }

            return app(MovementContext::class)->run((int) $locked->id, $data['kind'] === 'delivery' ? 'checkout' : 'checkin from', function (MovementContext $context) use ($locked, $operator, $data, $hash, $cycle, $user, $notebook): FioluxInspection {
                $at = now();
                if ($data['kind'] === 'delivery') {
                    static::ensure($locked->checkOut($user, $operator, $at->format('Y-m-d H:i:s'), null, trans('fiolux.movement_note', ['cycle' => $cycle->id]), $locked->name), 'movement_failed');
                    $notebook->last_cycle_id = $cycle->id;
                    $notebook->saveOrFail();
                } else {
                    $original = $locked->getRawOriginal();
                    $locked->assignedTo()->dissociate();
                    $locked->expected_checkin = null;
                    $locked->accepted = null;
                    $locked->last_checkin = $at;
                    $locked->status_id = $notebook->pending_status_id;
                    static::ensure($locked->save(), 'movement_failed');
                    event(new CheckoutableCheckedIn($locked, $user, $operator, trans('fiolux.movement_note', ['cycle' => $cycle->id]), $at->format('Y-m-d H:i:s'), $original));
                    $cycle->returned_at = $at;
                    $cycle->open_asset_id = null;
                    $cycle->saveOrFail();
                }

                return FioluxInspection::create([
                    'cycle_id' => $cycle->id,
                    'kind' => $data['kind'],
                    'operation_key' => $data['operation_key'],
                    'payload_hash' => $hash,
                    'operator_id' => $operator->id,
                    'actionlog_id' => $context->actionlogId(),
                    'template_version' => config('fiolux.template_version'),
                    'snapshot' => NotebookChecklist::snapshot($locked, $user, $operator, $data),
                ]);
            });
        }, 3);
    }

    private static function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['fiolux' => trans('fiolux.'.$message)]);
        }
    }
}
