<?php

use App\Actions\Fiolux\RecordMovementAction;
use App\Models\Actionlog;
use App\Models\Asset;
use App\Models\FioluxCycle;
use App\Models\FioluxInspection;
use App\Models\FioluxNotebook;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;

require __DIR__.'/../../../vendor/autoload.php';
$application = require __DIR__.'/../../../bootstrap/app.php';
$application->make(Kernel::class)->bootstrap();

if (! config('fiolux.enabled') || config('database.connections.mysql.database') !== 'teste_fiolux_poc' || config('mail.default') !== 'array') {
    throw new RuntimeException('Worker restrito ao banco fictício teste_fiolux_poc com notificações desativadas.');
}
$operator = User::where('username', 'teste_fiolux_ti')->firstOrFail();
$employee = User::where('username', 'teste_fiolux_a')->firstOrFail();
Auth::setUser($operator);
$asset = Asset::where('asset_tag', 'TESTE FIOLUX NB-001')->firstOrFail();
$notebook = FioluxNotebook::where('asset_id', $asset->id)->firstOrFail();

if (($argv[1] ?? '') === 'checkout') {
    $inspection = RecordMovementAction::run($asset, $operator, [
        'kind' => 'delivery', 'operation_key' => $argv[2] ?? '', 'user_id' => $employee->id,
        'answers' => ['screen' => 'avaria', 'case' => 'conforme', 'charger' => 'ausente', 'cable' => 'presente'],
        'reason' => 'TESTE FIOLUX - condição fictícia preservada do ciclo anterior.',
        'notes' => 'TESTE FIOLUX - entrega de teste concorrente; duas requisições com a mesma chave.',
        'confirmed' => 1,
    ]);
    echo json_encode(['inspection_id' => $inspection->id, 'cycle_id' => $inspection->cycle_id], JSON_THROW_ON_ERROR).PHP_EOL;
} else {
    $previous = $notebook->lastCycle;
    $ready = $asset->availableForCheckout() && (! $previous || ($previous->returned_at && $previous->evaluations()->orderByDesc('id')->first()?->result === 'available' && ! $previous->signaturePending()));
    echo json_encode([
        'ready' => $ready, 'assigned_to' => $asset->assigned_to,
        'cycles' => FioluxCycle::where('asset_id', $asset->id)->count(),
        'inspections' => FioluxInspection::whereIn('cycle_id', FioluxCycle::where('asset_id', $asset->id)->select('id'))->count(),
        'checkouts' => Actionlog::where('item_type', Asset::class)->where('item_id', $asset->id)->where('action_type', 'checkout')->count(),
    ], JSON_THROW_ON_ERROR).PHP_EOL;
}
