<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class Warehouse extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'address',
        'allow_negative_stock',
        'is_active',
    ];

    protected $casts = [
        'allow_negative_stock' => 'boolean',
        'is_active'            => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_warehouse')
            ->withTimestamps();
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ItemStock::class);
    }

    public function scopeForCompany(Builder $query, ?int $companyId): Builder
    {
        if (!$companyId) {
            return $query;
        }

        if (!Schema::hasTable('company_warehouse')) {
            return $query->where('company_id', $companyId);
        }

        return $query->where(function (Builder $q) use ($companyId) {
            $q->where('company_id', $companyId)
                ->orWhereHas('companies', fn (Builder $cq) => $cq->where('companies.id', $companyId));
        });
    }
}
