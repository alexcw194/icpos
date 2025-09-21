<?php

// app/Policies/CustomerPolicy.php
namespace App\Policies;

use App\Models\User;
use App\Models\Customer;

class CustomerPolicy
{
    public function view(User $user, Customer $c): bool
    {
        return $user->hasAnyRole(['admin','finance']) || $c->created_by === $user->id;
    }

    public function update(User $user, Customer $c): bool
    {
        return $user->hasAnyRole(['admin','finance']) || $c->created_by === $user->id;
    }

    public function delete(User $user, Customer $c): bool
    {
        return $user->hasAnyRole(['admin','finance']) || $c->created_by === $user->id;
    }
}
