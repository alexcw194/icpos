<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'number',
        'year',
        'sequence',
        'title',
        'body_html',
        'customer_id',
        'contact_id',
        'customer_snapshot',
        'contact_snapshot',
        'created_by_user_id',
        'status',
        'submitted_at',
        'admin_approved_by_user_id',
        'admin_approved_at',
        'approved_by_user_id',
        'approved_at',
        'rejected_by_user_id',
        'rejected_at',
        'rejection_note',
        'sales_signature_position',
        'approver_signature_position',
        'signatures',
    ];

    protected $casts = [
        'customer_snapshot' => 'array',
        'contact_snapshot' => 'array',
        'signatures' => 'array',
        'submitted_at' => 'datetime',
        'admin_approved_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    protected $appends = ['status_label', 'status_badge_class'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function adminApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_approved_by_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_user_id');
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (!$user) {
            return $query->whereRaw('1=0');
        }

        if ($user->hasAnyRole(['Admin', 'SuperAdmin'])) {
            return $query;
        }

        return $query->where('created_by_user_id', $user->id);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_SUBMITTED => 'Submitted',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            default => ucfirst((string) $this->status),
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'bg-yellow-lt text-yellow-9',
            self::STATUS_SUBMITTED => 'bg-blue-lt text-blue-9',
            self::STATUS_APPROVED => 'bg-green-lt text-green-9',
            self::STATUS_REJECTED => 'bg-red-lt text-red-9',
            default => 'bg-secondary-lt text-secondary-9',
        };
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REJECTED], true);
    }
}
