<?php

namespace App\Policies;

use App\Models\ProjectQuotation;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProjectQuotationPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ProjectQuotation $quotation): bool
    {
        if ($user->hasAnyRole(['Admin', 'SuperAdmin', 'Finance', 'Logistic'])) {
            return true;
        }

        return (int) $quotation->sales_owner_user_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'SuperAdmin']);
    }

    public function update(User $user, ProjectQuotation $quotation): bool
    {
        return $user->hasAnyRole(['Admin', 'SuperAdmin']);
    }

    public function delete(User $user, ProjectQuotation $quotation): bool
    {
        return $user->hasAnyRole(['Admin', 'SuperAdmin']);
    }

    public function issue(User $user, ProjectQuotation $quotation): bool
    {
        return $user->hasAnyRole(['Admin', 'SuperAdmin']);
    }

    public function markWon(User $user, ProjectQuotation $quotation): bool
    {
        return $user->hasAnyRole(['Admin', 'SuperAdmin']);
    }

    public function markLost(User $user, ProjectQuotation $quotation): bool
    {
        return $user->hasAnyRole(['Admin', 'SuperAdmin']);
    }
}
