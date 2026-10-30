<?php

namespace App\Console\Commands;

use App\Actions\Fiolux\RecordMovementAction;
use App\Actions\Fiolux\RecordTechnicalEvaluationAction;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\FioluxCycle;
use App\Models\FioluxInspection;
use App\Models\FioluxNotebook;
use App\Models\Manufacturer;
use App\Models\Setting;
use App\Models\Statuslabel;
use App\Models\User;
use App\Services\Fiolux\Documents;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class FioluxPrepareCommand extends Command
{
    protected $signature = 'fiolux:prepare {--demonstrate : Criar os dois ciclos fictícios se ainda não existirem}';

    protected $description = 'Prepara somente dados TESTE FIOLUX em banco dedicado à prova';

    public function handle(): int
    {
        if (! config('fiolux.enabled') || ! str_starts_with(config('database.connections.mysql.database', ''), 'teste_fiolux_') || config('mail.default') !== 'array') {
            $this->error('Exige FIOLUX_ENABLED, banco teste_fiolux_* e mailer array.');

            return self::FAILURE;
        }
        $password = config('fiolux.demo_password');
        if (! is_string($password) || strlen($password) < 16) {
            $this->error('Defina FIOLUX_DEMO_PASSWORD no arquivo .env local, sem publicar o valor.');

            return self::FAILURE;
        }

        $settings = Setting::first() ?? new Setting;
        $settings->site_name = 'TESTE FIOLUX - Prova de conceito';
        $settings->brand = 1;
        $settings->alerts_enabled = 0;
        $settings->locale = 'pt-BR';
        $settings->thumbnail_max_h = 200;
        $settings->auto_increment_assets = 0;
        $settings->saml_enabled = 0;
        $settings->ldap_enabled = 0;
        $settings->webhook_endpoint = null;
        $this->persist($settings);
        Setting::$_cache = null;

        $roles = [
            'admin' => ['superuser' => '1'],
            'ti' => ['assets.view' => '1', 'assets.checkout' => '1', 'assets.checkin' => '1', 'users.view' => '1', 'fiolux.view' => '1', 'fiolux.checkout' => '1', 'fiolux.checkin' => '1', 'fiolux.technical' => '1', 'fiolux.documents' => '1'],
            'rh' => ['assets.view' => '1', 'users.view' => '1', 'fiolux.view' => '1', 'fiolux.checkin' => '1', 'fiolux.documents' => '1'],
            'gestao' => ['assets.view' => '1', 'users.view' => '1', 'fiolux.view' => '1'],
            'a' => [],
            'b' => [],
        ];
        $users = [];
        foreach ($roles as $name => $permissions) {
            $user = User::firstOrNew(['username' => 'teste_fiolux_'.$name]);
            if (! $user->exists) {
                $user->first_name = 'TESTE FIOLUX';
                $user->last_name = strtoupper($name);
                $user->password = Hash::make($password);
                $user->permissions = json_encode($permissions);
                $user->activated = in_array($name, ['a', 'b']) ? 0 : 1;
                $user->email = null;
                $user->locale = 'pt-BR';
                $this->persist($user);
            }
            $users[$name] = $user;
        }
        Auth::setUser($users['ti']);

        $available = Statuslabel::firstOrNew(['name' => 'TESTE FIOLUX Disponível']);
        if (! $available->exists) {
            $available->fill(['deployable' => 1, 'pending' => 0, 'archived' => 0]);
            $this->persist($available);
        }
        $pending = Statuslabel::firstOrNew(['name' => 'TESTE FIOLUX Aguardando TI']);
        if (! $pending->exists) {
            $pending->fill(['deployable' => 0, 'pending' => 1, 'archived' => 0]);
            $this->persist($pending);
        }
        $category = Category::firstOrNew(['name' => 'TESTE FIOLUX Notebook']);
        if (! $category->exists) {
            $category->fill(['category_type' => 'asset', 'require_acceptance' => 0, 'checkin_email' => 0, 'use_default_eula' => 0]);
            $this->persist($category);
        }
        $manufacturer = Manufacturer::firstOrNew(['name' => 'TESTE FIOLUX Fabricante']);
        $this->persist($manufacturer);
        $model = AssetModel::firstOrNew(['name' => 'TESTE FIOLUX Modelo Notebook']);
        if (! $model->exists) {
            $model->fill(['category_id' => $category->id, 'manufacturer_id' => $manufacturer->id, 'model_number' => 'TESTE FIOLUX 001']);
            $this->persist($model);
        }
        $asset = Asset::firstOrNew(['asset_tag' => 'TESTE FIOLUX NB-001']);
        if (! $asset->exists) {
            $asset->fill(['name' => 'TESTE FIOLUX Notebook', 'model_id' => $model->id, 'serial' => 'TESTE FIOLUX SERIAL 001', 'status_id' => $available->id, 'created_by' => $users['ti']->id, 'requestable' => 0]);
            $this->persist($asset);
        }
        FioluxNotebook::firstOrCreate(['asset_id' => $asset->id], ['available_status_id' => $available->id, 'pending_status_id' => $pending->id]);

        if ($this->option('demonstrate') && ! FioluxCycle::where('asset_id', $asset->id)->exists()) {
            $documents = app(Documents::class);
            $normal = ['screen' => 'conforme', 'case' => 'conforme', 'charger' => 'presente', 'cable' => 'presente'];
            $changed = ['screen' => 'avaria', 'case' => 'conforme', 'charger' => 'ausente', 'cable' => 'presente'];
            foreach ([['a', $normal, $changed], ['b', $changed, $changed]] as [$person, $deliveryAnswers, $returnAnswers]) {
                Auth::setUser($users['ti']);
                $delivery = RecordMovementAction::run($asset, $users['ti'], $this->payload('delivery', $deliveryAnswers) + ['user_id' => $users[$person]->id]);
                $this->document($delivery, $users['ti'], $documents);
                Auth::setUser($users['rh']);
                $returned = RecordMovementAction::run($asset, $users['rh'], $this->payload('return', $returnAnswers) + ['cycle_id' => $delivery->cycle_id]);
                $this->document($returned, $users['rh'], $documents);
                Auth::setUser($users['ti']);
                RecordTechnicalEvaluationAction::run($delivery->cycle, $users['ti'], [
                    'operation_key' => (string) Str::uuid(), 'result' => 'available', 'confirmed' => 1,
                    'notes' => 'TESTE FIOLUX - liberação técnica fictícia; condições do checklist preservadas. Não representa reparo de dispositivo real.',
                ]);
            }
        }

        $this->info('Dados TESTE FIOLUX preparados. Contas: teste_fiolux_admin, teste_fiolux_ti, teste_fiolux_rh e teste_fiolux_gestao. Senha somente no .env local.');
        $this->line('Ativo: '.$asset->id.'; ciclos: '.FioluxCycle::where('asset_id', $asset->id)->count().'.');

        return self::SUCCESS;
    }

    private function persist(Model $model): void
    {
        if (! $model->save()) {
            throw new RuntimeException('Falha ao salvar '.get_class($model).': '.json_encode($model->getErrors()->all()));
        }
    }

    private function payload(string $kind, array $answers): array
    {
        return [
            'operation_key' => (string) Str::uuid(), 'kind' => $kind, 'answers' => $answers,
            'confirmed' => 1, 'notes' => 'TESTE FIOLUX - demonstração fictícia de '.$kind,
            'reason' => 'TESTE FIOLUX - condições simuladas para validar o registro; sem atribuição de responsabilidade.',
        ];
    }

    private function document(FioluxInspection $inspection, User $operator, Documents $documents): void
    {
        $generated = $documents->generate($inspection, $operator);
        if ($generated->state !== 'ready') {
            throw new RuntimeException('Falha na geração de documento de teste.');
        }
        $pdf = new \TCPDF;
        $pdf->setPrintHeader(false);
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->AddPage();
        $pdf->writeHTML(view('fiolux.pdf', compact('inspection'))->render().'<p>TESTE FIOLUX — CÓPIA FICTÍCIA COM ASSINATURAS SIMULADAS<br>Quem entrega: TESTE FIOLUX ENTREGADOR<br>Quem recebe: TESTE FIOLUX RECEBEDOR<br>SEM VALIDADE PARA OPERAÇÃO REAL</p>');
        $file = UploadedFile::fake()->createWithContent('TESTE-FIOLUX-assinado-ficticio.pdf', $pdf->Output('', 'S'));
        $signed = $documents->upload($inspection, $operator, $file);
        if ($signed->state !== 'ready') {
            throw new RuntimeException('Falha no upload de documento de teste.');
        }
        $documents->review($signed, $operator);
        Storage::put('private_uploads/fiolux-examples/TESTE-FIOLUX-'.$inspection->cycle_id.'-'.$inspection->kind.'.pdf', Storage::get($generated->path));
        Storage::put('private_uploads/fiolux-examples/TESTE-FIOLUX-'.$inspection->cycle_id.'-'.$inspection->kind.'-assinado-ficticio.pdf', Storage::get($signed->path));
    }
}
