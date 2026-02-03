<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingDocumentLine extends Model
{
    protected $fillable = [
        'billing_document_id','sales_order_line_id','position',
        'name','description','unit','qty','unit_price',
        'discount_type','discount_value','discount_amount',
        'line_subtotal','line_total',
    ];

    protected $casts = [
        'qty' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'line_subtotal' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function billingDocument(): BelongsTo
    {
        return $this->belongsTo(BillingDocument::class);
    }

    public function salesOrderLine(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class);
    }
}
