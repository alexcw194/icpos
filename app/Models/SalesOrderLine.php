<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderLine extends Model
{
    protected $fillable = [
        'sales_order_id','position',
        'name','po_item_name','description','unit',
        'qty_ordered','unit_price',
        'discount_type','discount_value','discount_amount',
        'line_subtotal','line_total',
        // NEW
        'item_id','item_variant_id',
    ];

    protected $casts = [
        'qty_ordered'     => 'decimal:2',
        'unit_price'      => 'decimal:2',
        'discount_value'  => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'line_subtotal'   => 'decimal:2',
        'line_total'      => 'decimal:2',
        // (opsional) bantu casting id
        'item_id'         => 'integer',
        'item_variant_id' => 'integer',
    ];

    public function salesOrder(): BelongsTo { return $this->belongsTo(SalesOrder::class); }

    // NEW
    public function item(): BelongsTo { return $this->belongsTo(Item::class); }
    public function variant(): BelongsTo { return $this->belongsTo(ItemVariant::class, 'item_variant_id'); }
}
