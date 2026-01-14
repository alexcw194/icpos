<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Project extends Model
{
    protected $fillable = [
        'company_id',
        'customer_id',
        'code',
        'name',
        'systems_json',
        'status',
        'sales_owner_user_id',
        'start_date',
        'target_finish_date',
        'contract_value_baseline',
        'contract_value_current',
        'notes',
    ];

    protected $casts = [
        'systems_json' => 'array',
        'start_date' => 'date',
        'target_finish_date' => 'date',
        'contract_value_baseline' => 'decimal:2',
        'contract_value_current' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_owner_user_id');
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(ProjectQuotation::class);
    }

    public function scopeVisibleTo(Builder $query, ?User $user = null): Builder
    {
        $u = $user ?: auth()->user();
        if (!$u) {
            return $query->whereRaw('1=0');
        }

        if ($u->hasAnyRole(['Admin', 'SuperAdmin', 'Finance', 'Logistic'])) {
            return $query;
        }

        return $query->where('sales_owner_user_id', $u->id);
    }
}
