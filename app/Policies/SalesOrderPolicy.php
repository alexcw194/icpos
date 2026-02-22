<?php

namespace App\Policies;

use App\Models\SalesOrder;
use App\Models\SalesOrderAttachment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SalesOrderPolicy
{
    use HandlesAuthorization;

    // View: Admin/SuperAdmin/Finance can view all, others only own SO.
    public function view(User $user, SalesOrder $so): bool
    {
        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Admin', 'SuperAdmin', 'Finance'])) {
            return true;
        }

        return (int) $so->sales_user_id === (int) $user->id;
    }

    // Edit header/lines only when OPEN and without DN/Invoice.
    public function update(User $user, SalesOrder $so): bool
    {
        return $this->isOpenAndUnlocked($so)
            && ($this->isAdmin($user) || $this->isSalesOwner($user, $so))
            || $this->isSuperAdmin($user);
    }

    // Cancel only when still active.
    public function cancel(User $user, SalesOrder $so): bool
    {
        if ($so->status === 'cancelled') {
            return false;
        }

        return $this->isSuperAdmin($user) || $this->isAdmin($user);
    }

    // Delete: SuperAdmin only, OPEN and no DN/Invoice.
    public function delete(User $user, SalesOrder $so): bool
    {
        return $this->isOpenAndUnlocked($so) && $this->isSuperAdmin($user);
    }

    // Upload attachment while OPEN for Admin or owner.
    public function uploadAttachment(User $user, SalesOrder $so): bool
    {
        return $so->status === 'open'
            && ($this->isAdmin($user) || $this->isSalesOwner($user, $so));
    }

    // Amend (VO) blocked on cancelled SO.
    public function amend(User $user, SalesOrder $so): bool
    {
        if ($so->status === 'cancelled') {
            return false;
        }

        return $this->isSuperAdmin($user)
            || $this->isAdmin($user)
            || $this->isSalesOwner($user, $so);
    }

    public function deleteAttachment(User $user, SalesOrder $so, ?SalesOrderAttachment $att = null): bool
    {
        if (!$this->isOpenAndUnlocked($so)) {
            return false;
        }

        if ($this->isSuperAdmin($user) || $this->isAdmin($user)) {
            return true;
        }

        if ($att) {
            return (int) $att->uploaded_by_user_id === (int) $user->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Sales', 'SalesManager', 'Admin', 'SuperAdmin']);
    }

    protected function isOpenAndUnlocked(SalesOrder $so): bool
    {
        if ($so->status !== 'open') {
            return false;
        }

        $hasDn = method_exists($so, 'deliveries') ? $so->deliveries()->exists() : false;
        $hasInv = method_exists($so, 'invoices') ? $so->invoices()->exists() : false;

        return !($hasDn || $hasInv);
    }

    protected function isSalesOwner(User $user, SalesOrder $so): bool
    {
        return (int) $so->sales_user_id === (int) $user->id;
    }

    protected function isAdmin(User $user): bool
    {
        return method_exists($user, 'isAdmin') && $user->isAdmin();
    }

    protected function isSuperAdmin(User $user): bool
    {
        return method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();
    }
}
