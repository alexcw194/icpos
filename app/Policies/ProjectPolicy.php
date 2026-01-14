<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProjectPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        if ($user->hasAnyRole(['Admin', 'SuperAdmin', 'Finance', 'Logistic'])) {
            return true;
        }

        return (int) $project->sales_owner_user_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'SuperAdmin']);
    }

    public function update(User $user, Project $project): bool
    {
        return $user->hasAnyRole(['Admin', 'SuperAdmin']);
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->hasAnyRole(['Admin', 'SuperAdmin']);
    }
}
