<?php

namespace App\Services\Fiolux;

use App\Models\Actionlog;
use App\Models\Asset;
use App\Models\FioluxNotebook;
use Closure;
use Illuminate\Validation\ValidationException;

class MovementContext
{
    private ?int $assetId = null;

    private ?string $action = null;

    private array $actionlogs = [];

    public function run(int $assetId, string $action, Closure $callback): mixed
    {
        $previous = [$this->assetId, $this->action, $this->actionlogs];
        [$this->assetId, $this->action, $this->actionlogs] = [$assetId, $action, []];
        try {
            return $callback($this);
        } finally {
            [$this->assetId, $this->action, $this->actionlogs] = $previous;
        }
    }

    public function assertAllowed(Asset $asset): void
    {
        if (config('fiolux.enabled') && $asset->exists && $this->assetId !== (int) $asset->id && FioluxNotebook::where('asset_id', $asset->id)->exists()) {
            throw ValidationException::withMessages(['fiolux' => trans('fiolux.use_flow')]);
        }
    }

    public function capture(Actionlog $log): void
    {
        if ($log->item_type === Asset::class && (int) $log->item_id === $this->assetId && $log->action_type === $this->action) {
            $this->actionlogs[] = (int) $log->id;
        }
    }

    public function actionlogId(): int
    {
        if (count($this->actionlogs) !== 1) {
            throw ValidationException::withMessages(['fiolux' => trans('fiolux.movement_link_failed')]);
        }

        return $this->actionlogs[0];
    }
}
