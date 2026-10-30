<?php

namespace App\Actions\Fiolux;

use App\Models\Actionlog;
use App\Models\Asset;
use App\Models\FioluxCycle;
use App\Observers\FioluxAssetObserver;
use App\Policies\FioluxCyclePolicy;
use App\Services\Fiolux\MovementContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Lang;

class RegisterFioluxAction
{
    public static function run(): void
    {
        app()->singleton(MovementContext::class);
        Gate::policy(FioluxCycle::class, FioluxCyclePolicy::class);
        Asset::observe(FioluxAssetObserver::class);
        Actionlog::created(fn (Actionlog $log) => app(MovementContext::class)->capture($log));
        $permissions = [];
        foreach (['view', 'checkout', 'checkin', 'technical', 'documents'] as $permission) {
            $permissions[] = ['permission' => 'fiolux.'.$permission, 'display' => true];
        }
        config(['permissions.Fiolux' => $permissions]);
        foreach (['pt-BR', 'en-US'] as $locale) {
            $lines = ['permissions.fiolux.name' => trans('fiolux.title', [], $locale)];
            foreach (['view', 'checkout', 'checkin', 'technical', 'documents'] as $permission) {
                $lines['permissions.fiolux'.$permission.'.name'] = trans('fiolux.permission_'.$permission, [], $locale);
            }
            Lang::addLines($lines, $locale);
        }
    }
}
