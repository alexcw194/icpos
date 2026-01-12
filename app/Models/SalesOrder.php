<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Database\Eloquent\Casts\Attribute;

class SalesOrder extends Model
{
    protected $fillable = [
        'company_id','customer_id','quotation_id','sales_user_id',
        'so_number','order_date',
        'customer_po_number','customer_po_date','deadline',
        'ship_to','bill_to','notes',
        'private_notes','under_amount',
        'discount_mode',
        'lines_subtotal','total_discount_type','total_discount_value','total_discount_amount',
        'taxable_base','tax_percent','tax_amount','total',
        'npwp_required','npwp_status','tax_npwp_number','tax_npwp_name','tax_npwp_address',
        'status',
        // NEW
        'brand_snapshot','currency',
        'cancelled_at','cancelled_by_user_id','cancel_reason',
    ];

    protected $casts = [
        // tanggal
        'order_date'        => 'date',
        'customer_po_date'  => 'date',
        'deadline'          => 'date',
        'cancelled_at'      => 'datetime',

        // angka (2 desimal)
        'lines_subtotal'        => 'decimal:2',
        'total_discount_value'  => 'decimal:2',
        'total_discount_amount' => 'decimal:2',
        'taxable_base'          => 'decimal:2',
        'tax_percent'           => 'decimal:2',
        'tax_amount'            => 'decimal:2',
        'total'                 => 'decimal:2',
        'under_amount'          => 'decimal:2',

        // boolean
        'npwp_required' => 'bool',

        // snapshot
        'brand_snapshot' => 'array',
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

    // ---------------------------
    // Alias akses lama/baru
    // ---------------------------

    /**
     * Alias: $so->number <-> kolom so_number
     */
    protected function number(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->so_number,
            set: fn ($value) => ['so_number' => $value],
        );
    }

    /**
     * Alias: $so->date <-> kolom order_date
     */
    protected function date(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->order_date,
            set: fn ($value) => ['order_date' => $value],
        );
    }

    // ---------------------------
    // Relations
    // ---------------------------
    public function company(): BelongsTo   { return $this->belongsTo(Company::class); }
    public function customer(): BelongsTo  { return $this->belongsTo(Customer::class); }
    public function quotation(): BelongsTo { return $this->belongsTo(Quotation::class); }
    public function salesUser(): BelongsTo { return $this->belongsTo(User::class, 'sales_user_id'); }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class)->orderBy('position');
    }

    public function attachments(){ return $this->hasMany(SalesOrderAttachment::class); }
}
