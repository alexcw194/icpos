<?php

namespace App\Policies;

use App\Models\Prospect;
use App\Models\User;

class ProspectPolicy
{
    private function isAdmin(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'SuperAdmin', 'Super Admin']);
    }

    private function isSales(User $user): bool
    {
        return $user->hasRole('Sales');
    }

    private function canViewNewLeadForSales(User $user, Prospect $prospect): bool
    {
        return $this->isSales($user)
            && (int) $prospect->owner_user_id === (int) $user->id
            && $prospect->status === Prospect::STATUS_ASSIGNED;
    }

    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, Prospect $prospect): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, Prospect $prospect): bool
    {
        return $this->isAdmin($user);
    }

    public function viewNewLeadsAny(User $user): bool
    {
        return $this->isAdmin($user) || $this->isSales($user);
    }

    public function viewNewLead(User $user, Prospect $prospect): bool
    {
        if ($this->isAdmin($user)) {
            return in_array($prospect->status, [
                Prospect::STATUS_ASSIGNED,
                Prospect::STATUS_REJECTED,
                Prospect::STATUS_CONVERTED,
            ], true);
        }

        return $this->canViewNewLeadForSales($user, $prospect);
    }

    public function reject(User $user, Prospect $prospect): bool
    {
        if ($this->isAdmin($user)) {
            return in_array($prospect->status, [Prospect::STATUS_ASSIGNED, Prospect::STATUS_REJECTED], true);
        }

        return $this->canViewNewLeadForSales($user, $prospect);
    }

    public function reassign(User $user, Prospect $prospect): bool
    {
        return $this->isAdmin($user);
    }

    public function addAsCustomer(User $user, Prospect $prospect): bool
    {
        if ($prospect->status === Prospect::STATUS_CONVERTED) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return in_array($prospect->status, [Prospect::STATUS_ASSIGNED, Prospect::STATUS_REJECTED], true);
        }

        return $this->canViewNewLeadForSales($user, $prospect);
    }
}
