<?php

namespace App\Policies;

use App\Models\Prospect;
use App\Models\User;

class ProspectPolicy
{
    private function canAccess(User $user): bool
    {
        return $user->hasAnyRole(['Sales', 'Admin', 'SuperAdmin', 'Super Admin', 'Finance']);
    }

    public function viewAny(User $user): bool
    {
        return $this->canAccess($user);
    }

    public function view(User $user, Prospect $prospect): bool
    {
        return $this->canAccess($user);
    }

    public function update(User $user, Prospect $prospect): bool
    {
        return $this->canAccess($user);
    }
}
