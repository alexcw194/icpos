<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function before(User $u, $ability)
    {
        if ($u->hasRole('SuperAdmin')) {
            return true;
        }
    }

    public function viewAny(User $u)
    {
        return $u->hasAnyRole(['Admin', 'SuperAdmin', 'Finance']);
    }

    public function view(User $u, Invoice $i)
    {
        return $u->hasAnyRole(['Admin', 'SuperAdmin', 'Finance']);
    }

    public function create(User $u)
    {
        return $u->hasAnyRole(['Admin', 'SuperAdmin', 'Finance']);
    }

    public function update(User $u, Invoice $i)
    {
        return $u->hasAnyRole(['Admin', 'SuperAdmin', 'Finance']);
    }

    public function delete(User $u, Invoice $i)
    {
        return $u->hasRole('SuperAdmin');
    }
}

