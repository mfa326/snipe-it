<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\FioluxCycle;
use App\Models\FioluxNotebook;
use App\Models\Location;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class FioluxMeetingCommand extends Command
{
    protected $signature = 'fiolux:meeting {--next : Criar outro kit, preservando todas as demonstrações anteriores}';

    protected $description = 'Prepara um notebook livre e colaboradores próprios para o roteiro da reunião Fiolux';

    public function handle(): int
    {
        if (! config('fiolux.enabled') || ! str_starts_with(config('database.connections.mysql.database', ''), 'teste_fiolux_') || config('mail.default') !== 'array') {
            $this->error('Exige Fiolux habilitado, banco teste_fiolux_* e mailer array.');

            return self::FAILURE;
        }
        $password = config('fiolux.demo_password');
        if (! is_string($password) || strlen($password) < 16) {
            $this->error('Configure FIOLUX_DEMO_PASSWORD no ambiente privado.');

            return self::FAILURE;
        }
        $operator = User::where('username', 'teste_fiolux_ti')->firstOrFail();
        $reference = Asset::where('asset_tag', 'TESTE FIOLUX NB-001')->firstOrFail();
        $referenceNotebook = FioluxNotebook::where('asset_id', $reference->id)->firstOrFail();
        Auth::setUser($operator);
        $number = 1;
        if ($this->option('next')) {
            while (Asset::withTrashed()->where('asset_tag', 'TESTE FIOLUX DEMO NB-'.sprintf('%03d', $number))->exists()) {
                $number++;
            }
        }
        $code = sprintf('%03d', $number);
        $kit = DB::transaction(function () use ($code, $password, $reference, $referenceNotebook, $operator): array {
            $location = Location::firstOrNew(['name' => 'TESTE FIOLUX Sala de Demonstração']);
            if (! $location->exists) {
                $this->persist($location);
            }
            $users = [];
            foreach (['a', 'b'] as $person) {
                $user = User::firstOrNew(['username' => 'teste_fiolux_demo_'.$code.'_'.$person]);
                if (! $user->exists) {
                    $user->first_name = 'TESTE FIOLUX';
                    $user->last_name = 'DEMO '.$code.' '.strtoupper($person);
                    $user->password = Hash::make($password);
                    $user->activated = 0;
                    $user->email = null;
                    $user->permissions = '{}';
                    $user->locale = 'pt-BR';
                    $user->location_id = $location->id;
                    $this->persist($user);
                }
                $users[$person] = $user;
            }
            $asset = Asset::withTrashed()->firstOrNew(['asset_tag' => 'TESTE FIOLUX DEMO NB-'.$code]);
            if ($asset->trashed()) {
                throw new RuntimeException('Kit anterior excluído. Use --next; não restaurar ou apagar históricos.');
            }
            if (! $asset->exists) {
                $asset->fill([
                    'name' => 'TESTE FIOLUX Notebook da Reunião '.$code, 'serial' => 'TESTE FIOLUX DEMO SERIAL '.$code,
                    'model_id' => $reference->model_id, 'status_id' => $referenceNotebook->available_status_id,
                    'created_by' => $operator->id, 'requestable' => 0,
                    'location_id' => $location->id, 'rtd_location_id' => $location->id,
                ]);
                $this->persist($asset);
                FioluxNotebook::create([
                    'asset_id' => $asset->id, 'available_status_id' => $referenceNotebook->available_status_id,
                    'pending_status_id' => $referenceNotebook->pending_status_id,
                ]);
            }

            return compact('asset', 'users');
        });
        $cycles = FioluxCycle::where('asset_id', $kit['asset']->id)->count();
        $this->info('Kit da reunião: '.$kit['asset']->asset_tag.' | ciclos existentes: '.$cycles);
        $this->line('Ativo: '.route('hardware.show', $kit['asset']->id));
        $this->line('Conferências: '.route('fiolux.index', ['asset' => $kit['asset']->id]));
        foreach ($kit['users'] as $person => $user) {
            $this->line(strtoupper($person).': '.$user->first_name.' '.$user->last_name.' | '.route('users.show', $user->id));
        }
        if ($cycles > 0) {
            $this->warn('Este kit já foi utilizado e foi preservado. Para outra reunião, execute fiolux:meeting --next.');
        } else {
            $this->info('Pronto para iniciar a entrega a A. Nenhuma movimentação foi pré-executada neste kit.');
        }

        return self::SUCCESS;
    }

    private function persist(Model $model): void
    {
        if (! $model->save()) {
            throw new RuntimeException('Falha ao preparar '.get_class($model).': '.json_encode($model->getErrors()->all()));
        }
    }
}
