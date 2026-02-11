<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchasePriceHistory extends Model
{
    protected $fillable = [
        'item_id',
        'item_variant_id',
        'price',
        'effective_date',
        'purchase_order_id',
        'purchase_order_line_id',
        'source_company_id',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'effective_date' => 'date',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class, 'item_variant_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }
}
