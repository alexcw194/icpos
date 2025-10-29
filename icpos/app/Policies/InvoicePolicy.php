<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Invoice;

class InvoicePolicy
{
    // SuperAdmin bypass
    public function before(User $u, $ability) {
        if ($u->hasRole('SuperAdmin')) return true;
    }

    public function viewAny(User $u)   { return $u->hasAnyRole(['Admin','SuperAdmin']); }
    public function view(User $u, Invoice $i){ return $u->hasAnyRole(['Admin','SuperAdmin']); }
    public function create(User $u)    { return $u->hasAnyRole(['Admin','SuperAdmin']); }
    public function update(User $u, Invoice $i){ return $u->hasAnyRole(['Admin','SuperAdmin']); }
    public function delete(User $u, Invoice $i){ return $u->hasRole('SuperAdmin'); }
}