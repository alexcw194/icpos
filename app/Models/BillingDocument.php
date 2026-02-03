<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingDocument extends Model
{
    protected $fillable = [
        'sales_order_id','company_id','customer_id',
        'status','mode',
        'pi_number','pi_revision','pi_issued_at',
        'inv_number','invoice_date','issued_at','locked_at','ar_posted_at',
        'subtotal','discount_amount','tax_percent','tax_amount','total',
        'currency','notes',
        'voided_at','void_reason','replaced_by_id',
        'created_by','updated_by',
    ];

    protected $casts = [
        'pi_issued_at' => 'datetime',
        'issued_at' => 'datetime',
        'locked_at' => 'datetime',
        'ar_posted_at' => 'datetime',
        'invoice_date' => 'date',
        'voided_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_percent' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'pi_revision' => 'integer',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BillingDocumentLine::class)->orderBy('position');
    }

    public function replacement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_id');
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null || $this->status === 'void';
    }

    public function isEditable(): bool
    {
        return $this->status !== 'void' && $this->locked_at === null;
    }
}
