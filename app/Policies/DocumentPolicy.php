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
        return $user->hasAnyRole(['Sales', 'Admin', 'SuperAdmin']);
    }

    public function update(User $user, Document $document): bool
    {
        if ($user->hasAnyRole(['Admin', 'SuperAdmin'])) {
            return true;
        }

        return (int) $document->created_by_user_id === (int) $user->id;
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
            && !$document->approved_at;
    }

    public function reject(User $user, Document $document): bool
    {
        return $user->hasAnyRole(['Admin', 'SuperAdmin'])
            && $document->status === Document::STATUS_SUBMITTED;
    }

    public function delete(User $user, Document $document): bool
    {
        if ($user->hasAnyRole(['Admin', 'SuperAdmin'])) {
            return true;
        }

        return (int) $document->created_by_user_id === (int) $user->id
            && $document->status === Document::STATUS_DRAFT;
    }
}
