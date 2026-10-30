<?php

namespace App\Observers;

use App\Models\Asset;
use App\Services\Fiolux\MovementContext;

class FioluxAssetObserver
{
    public function saving(Asset $asset): void
    {
        if ($asset->exists && $asset->isDirty(['assigned_to', 'assigned_type', 'status_id', 'model_id', 'company_id'])) {
            app(MovementContext::class)->assertAllowed($asset);
        }
    }

    public function deleting(Asset $asset): void
    {
        app(MovementContext::class)->assertAllowed($asset);
    }
}
