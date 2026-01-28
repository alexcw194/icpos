<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderBillingTerm extends Model
{
    protected $table = 'so_billing_terms';

    protected $fillable = [
        'sales_order_id',
        'seq',
        'top_code',
        'percent',
        'note',
        'status',
        'invoice_id',
    ];

    protected $casts = [
        'percent' => 'decimal:2',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(TermOfPayment::class, 'top_code', 'code');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
