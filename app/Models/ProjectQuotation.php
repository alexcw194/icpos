<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Builder;

class ProjectQuotation extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_WON = 'won';
    public const STATUS_LOST = 'lost';
    public const STATUS_SUPERSEDED = 'superseded';

    protected $fillable = [
        'project_id',
        'company_id',
        'customer_id',
        'number',
        'version',
        'status',
        'quotation_date',
        'to_name',
        'attn_name',
        'project_title',
        'working_time_days',
        'working_time_hours_per_day',
        'validity_days',
        'tax_enabled',
        'tax_percent',
        'subtotal_material',
        'subtotal_labor',
        'subtotal',
        'tax_amount',
        'grand_total',
        'notes',
        'signatory_name',
        'signatory_title',
        'issued_at',
        'won_at',
        'lost_at',
        'sales_owner_user_id',
        'brand_snapshot',
    ];

    protected $casts = [
        'quotation_date' => 'date',
        'issued_at' => 'datetime',
        'won_at' => 'datetime',
        'lost_at' => 'datetime',
        'tax_enabled' => 'boolean',
        'tax_percent' => 'decimal:2',
        'subtotal_material' => 'decimal:2',
        'subtotal_labor' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'brand_snapshot' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

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

    public function sections(): HasMany
    {
        return $this->hasMany(ProjectQuotationSection::class)->orderBy('sort_order');
    }

    public function paymentTerms(): HasMany
    {
        return $this->hasMany(ProjectQuotationPaymentTerm::class)->orderBy('sequence');
    }

    public function lines(): HasManyThrough
    {
        return $this->hasManyThrough(ProjectQuotationLine::class, ProjectQuotationSection::class, 'project_quotation_id', 'section_id');
    }

    public function isLocked(): bool
    {
        return in_array($this->status, [self::STATUS_ISSUED, self::STATUS_WON, self::STATUS_LOST, self::STATUS_SUPERSEDED], true);
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
