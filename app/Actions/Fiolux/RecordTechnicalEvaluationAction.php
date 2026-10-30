<?php

namespace App\Actions\Fiolux;

use App\Http\Requests\Fiolux\TechnicalRequest;
use App\Models\Asset;
use App\Models\FioluxCycle;
use App\Models\FioluxNotebook;
use App\Models\FioluxTechnicalEvaluation;
use App\Models\User;
use App\Services\Fiolux\MovementContext;
use App\Services\Fiolux\NotebookChecklist;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RecordTechnicalEvaluationAction
{
    public static function run(FioluxCycle $cycle, User $operator, array $data): FioluxTechnicalEvaluation
    {
        abort_unless(config('fiolux.enabled') && auth()->id() === $operator->id, 403);
        Gate::authorize('technical', $cycle);
        $data = Validator::make($data, (new TechnicalRequest)->rules())->validate();
        $hash = hash('sha256', json_encode([$cycle->id, $data['result'], $data['notes']], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($cycle, $operator, $data, $hash): FioluxTechnicalEvaluation {
            $asset = Asset::whereKey($cycle->asset_id)->lockForUpdate()->firstOrFail();
            $notebook = FioluxNotebook::where('asset_id', $asset->id)->lockForUpdate()->firstOrFail();
            $cycle = FioluxCycle::whereKey($cycle->id)->lockForUpdate()->firstOrFail();
            $existing = FioluxTechnicalEvaluation::where('operation_key', $data['operation_key'])->first();
            if ($existing) {
                if ($existing->payload_hash !== $hash || (int) $existing->operator_id !== (int) $operator->id) {
                    throw ValidationException::withMessages(['fiolux' => trans('fiolux.duplicate_conflict')]);
                }

                return $existing;
            }
            if (! $cycle->returned_at || $asset->assigned_to || (int) $notebook->last_cycle_id !== (int) $cycle->id) {
                throw ValidationException::withMessages(['fiolux' => trans('fiolux.wrong_cycle')]);
            }

            return app(MovementContext::class)->run((int) $asset->id, 'technical', function () use ($asset, $notebook, $cycle, $operator, $data, $hash): FioluxTechnicalEvaluation {
                $asset->status_id = $data['result'] === 'available' ? $notebook->available_status_id : $notebook->pending_status_id;
                if (! $asset->save()) {
                    throw ValidationException::withMessages(['fiolux' => trans('fiolux.movement_failed')]);
                }

                return FioluxTechnicalEvaluation::create([
                    'cycle_id' => $cycle->id, 'operation_key' => $data['operation_key'], 'payload_hash' => $hash,
                    'operator_id' => $operator->id, 'operator_snapshot' => NotebookChecklist::person($operator),
                    'result' => $data['result'], 'notes' => $data['notes'],
                ]);
            });
        }, 3);
    }
}
