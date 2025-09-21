<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class SalesOrder extends Model
{
    protected $fillable = [
        'company_id','customer_id','quotation_id','sales_user_id',
        'so_number','order_date',
        'customer_po_number','customer_po_date','deadline',
        'ship_to','bill_to','notes',
        'discount_mode',
        'lines_subtotal','total_discount_type','total_discount_value','total_discount_amount',
        'taxable_base','tax_percent','tax_amount','total',
        'npwp_required','npwp_status','tax_npwp_number','tax_npwp_name','tax_npwp_address',
        'status',

        // NEW (Langkah 1 & 8)
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

        // boolean
        'npwp_required' => 'bool',

        // snapshot
        'brand_snapshot' => 'array',
    ];

    public function company(): BelongsTo   { return $this->belongsTo(Company::class); }
    public function customer(): BelongsTo  { return $this->belongsTo(Customer::class); }
    public function quotation(): BelongsTo { return $this->belongsTo(Quotation::class); }
    public function salesUser(): BelongsTo { return $this->belongsTo(User::class, 'sales_user_id'); }
    public function lines(): HasMany       { return $this->hasMany(SalesOrderLine::class)->orderBy('position'); }
    public function attachments(): HasMany { return $this->hasMany(SalesOrderAttachment::class); }
}
