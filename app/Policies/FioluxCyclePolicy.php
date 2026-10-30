<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\Company;
use App\Models\FioluxCycle;
use App\Models\User;

class FioluxCyclePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAccess('fiolux.view');
    }

    public function view(User $user, FioluxCycle $cycle): bool
    {
        return $this->viewAny($user) && $cycle->asset instanceof Asset && Company::isCurrentUserHasAccess($cycle->asset) && $cycle->user instanceof User && Company::isCurrentUserHasAccess($cycle->user);
    }

    public function checkout(User $user): bool
    {
        return $this->viewAny($user) && $user->hasAccess('fiolux.checkout');
    }

    public function checkin(User $user, FioluxCycle $cycle): bool
    {
        return $this->view($user, $cycle) && $user->hasAccess('fiolux.checkin');
    }

    public function technical(User $user, FioluxCycle $cycle): bool
    {
        return $this->view($user, $cycle) && $user->hasAccess('fiolux.technical');
    }

    public function documents(User $user, FioluxCycle $cycle): bool
    {
        return $this->view($user, $cycle) && $user->hasAccess('fiolux.documents');
    }
}
