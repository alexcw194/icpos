<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    private function isPrivileged(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'SuperAdmin', 'Super Admin', 'Finance']);
    }

    private function isReadPrivileged(User $user): bool
    {
        return $this->isPrivileged($user) || $user->hasRole('Dokumen');
    }

    private function isOwner(User $user, Customer $customer): bool
    {
        // owner utama: sales_user_id
        if ($customer->sales_user_id) {
            return (int) $customer->sales_user_id === (int) $user->id;
        }

        // fallback legacy: created_by
        return (int) $customer->created_by === (int) $user->id;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->isReadPrivileged($user) || $this->isOwner($user, $customer);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->isPrivileged($user) || $this->isOwner($user, $customer);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $this->isPrivileged($user) || $this->isOwner($user, $customer);
    }
}
