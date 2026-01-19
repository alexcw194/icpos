<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'SuperAdmin']);
    }

    public function view(User $user, Document $document): bool
    {
        if ($user->hasAnyRole(['Admin', 'SuperAdmin'])) {
            return true;
        }

        return (int) $document->created_by_user_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Sales');
    }

    public function update(User $user, Document $document): bool
    {
        return (int) $document->created_by_user_id === (int) $user->id
            && $document->isEditable();
    }

    public function submit(User $user, Document $document): bool
    {
        return $this->update($user, $document);
    }

    public function approve(User $user, Document $document): bool
    {
        if (!$user->hasAnyRole(['Admin', 'SuperAdmin'])) {
            return false;
        }

        return $document->status === Document::STATUS_SUBMITTED
            && !$document->admin_approved_at;
    }

    public function finalApprove(User $user, Document $document): bool
    {
        if (!$user->hasRole('SuperAdmin')) {
            return false;
        }

        return $document->status === Document::STATUS_SUBMITTED
            && $document->admin_approved_at
            && !$document->approved_at;
    }

    public function reject(User $user, Document $document): bool
    {
        return $user->hasAnyRole(['Admin', 'SuperAdmin'])
            && $document->status === Document::STATUS_SUBMITTED;
    }
}
