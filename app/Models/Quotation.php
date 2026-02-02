<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};

class Quotation extends Model
{
    // === Status constants (final) ===
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT  = 'sent';
    public const STATUS_WON   = 'won';

    protected $appends = ['status_label', 'status_badge_class'];

    protected $fillable = [
        // --- header umum ---
        'company_id', 'customer_id', 'sales_user_id',
        'number', 'date', 'valid_until', 'status',
        'notes', 'terms', 'currency', 'brand_snapshot',

        // opsional jika kolom ada:
        'sales_order_id', // latest/primary SO (opsional)
        'won_at',

        // --- discount mode ---
        'discount_mode', // 'total' | 'per_item'

        // --- rekap Discount v2 ---
        'lines_subtotal',
        'total_discount_type', 'total_discount_value', 'total_discount_amount',
        'taxable_base', 'tax_percent', 'tax_amount', 'total',
    ];

    protected $casts = [
        'brand_snapshot'        => 'array',
        'date'                  => 'date',
        'valid_until'           => 'date',

        'discount_mode'         => 'string',
        'total_discount_value'  => 'decimal:2',
        'total_discount_amount' => 'decimal:2',
        'lines_subtotal'        => 'decimal:2',
        'taxable_base'          => 'decimal:2',
        'tax_percent'           => 'decimal:2',
        'tax_amount'            => 'decimal:2',
        'total'                 => 'decimal:2',

        'sent_at'               => 'datetime',
        'won_at'                => 'datetime',
    ];

    /**
     * Scope visibility:
     * - Admin/SuperAdmin: bisa lihat semua
     * - selain itu: hanya data milik sendiri (sales_user_id = user.id)
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (!$user) {
            return $query->whereRaw('1=0');
        }

        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Admin', 'SuperAdmin'])) {
            return $query;
        }

        return $query->where('sales_user_id', $user->id);
    }

    // ---- Relations ----
    public function lines(): HasMany
    {
        return $this->hasMany(QuotationLine::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function salesUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_user_id');
    }

    /**
     * Semua Sales Orders yang dibuat dari quotation ini (repeat order friendly).
     */
    public function salesOrders(): HasMany
    {
        return $this->hasMany(\App\Models\SalesOrder::class, 'quotation_id');
    }

    /**
     * Opsional: SO “utama/terakhir” bila tabel quotations punya kolom sales_order_id.
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(\App\Models\SalesOrder::class, 'sales_order_id');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(\App\Models\Invoice::class);
    }

    // Opsi A: delivery langsung terkait quotation (jika deliveries punya quotation_id)
    public function delivery(): HasOne
    {
        return $this->hasOne(\App\Models\Delivery::class);
    }

    // ---- Accessors ----
    public function getStatusBadgeClassAttribute(): string
    {
        // Tabler: warna pastel (light)
        return match ($this->status) {
            'draft' => 'bg-blue-lt text-blue-9',
            'sent'  => 'bg-blue-lt text-blue-9',
            'won'   => 'bg-green-lt text-green-9',
            default => 'bg-secondary-lt text-secondary-9',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'Sent',
            'sent'  => 'Sent',
            'won'   => 'Won',
            default => ucfirst((string) $this->status),
        };
    }

    public function getTotalIdrAttribute(): string
    {
        return 'Rp ' . number_format((float) $this->total, 2, ',', '.');
    }
}
