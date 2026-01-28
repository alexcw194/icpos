<?php

namespace App\Policies;

use App\Models\SalesOrder;
use App\Models\SalesOrderAttachment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SalesOrderPolicy
{
    use HandlesAuthorization;

    /** View: Admin/SuperAdmin lihat semua, selain itu hanya SO miliknya */
    public function view(User $user, SalesOrder $so): bool
    {
        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Admin', 'SuperAdmin'])) {
            return true;
        }

        return (int) $so->sales_user_id === (int) $user->id;
    }

    /** Edit header/lines — hanya saat OPEN & belum punya DN/Invoice */
    public function update(User $user, SalesOrder $so): bool
    {
        return $this->isOpenAndUnlocked($so) &&
               ($this->isAdmin($user) || $this->isSalesOwner($user, $so)) || $this->isSuperAdmin($user);
    }

    /** Cancel — hanya saat OPEN & belum punya DN/Invoice */
    public function cancel(User $user, SalesOrder $so): bool
    {
        if ($so->status === 'cancelled') {
            return false;
        }

        return $this->isSuperAdmin($user) || $this->isAdmin($user);
    }

    /** Delete — SuperAdmin saja, OPEN & belum punya DN/Invoice */
    public function delete(User $user, SalesOrder $so): bool
    {
        return $this->isOpenAndUnlocked($so) && $this->isSuperAdmin($user);
    }

    /** Upload lampiran — saat OPEN, Admin atau Sales owner */
    public function uploadAttachment(User $user, SalesOrder $so): bool
    {
        return $so->status === 'open' &&
               ($this->isAdmin($user) || $this->isSalesOwner($user, $so));
    }

    /** Amend (VO) — tidak boleh jika cancelled */
    public function amend(User $user, SalesOrder $so): bool
    {
        if ($so->status === 'cancelled') {
            return false;
        }

        return $this->isSuperAdmin($user)
            || $this->isAdmin($user)
            || $this->isSalesOwner($user, $so);
    }

    /**
     * Hapus lampiran:
     * - Admin/SuperAdmin: boleh saat OPEN & belum DN/INV
     * - Uploader: boleh jika OPEN & belum DN/INV dan dia uploader-nya
     * Catatan: bisa dipanggil sebagai Gate::authorize('deleteAttachment', [$so, $att])
     */
    public function deleteAttachment(User $user, SalesOrder $so, ?SalesOrderAttachment $att = null): bool
    {
        if (! $this->isOpenAndUnlocked($so)) return false;

        if ($this->isSuperAdmin($user) || $this->isAdmin($user)) {
            return true;
        }

        if ($att) {
            return (int)$att->uploaded_by_user_id === (int)$user->id;
        }

        return false;
    }

    // ----------------- Helpers -----------------

    /** OPEN dan belum punya dokumen turunan (DN/Invoice) */
    protected function isOpenAndUnlocked(SalesOrder $so): bool
    {
        if ($so->status !== 'open') return false;

        // Aman bila relasi belum ada: hanya cek jika method-nya tersedia
        $hasDn  = method_exists($so, 'deliveries') ? $so->deliveries()->exists() : false;
        $hasInv = method_exists($so, 'invoices')   ? $so->invoices()->exists()   : false;

        return !($hasDn || $hasInv);
    }

    protected function isSalesOwner(User $user, SalesOrder $so): bool
    {
        return (int)$so->sales_user_id === (int)$user->id;
    }

    protected function isAdmin(User $user): bool
    {
        return method_exists($user, 'isAdmin') && $user->isAdmin();
    }

    protected function isSuperAdmin(User $user): bool
    {
        return method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return true;
    }
}
