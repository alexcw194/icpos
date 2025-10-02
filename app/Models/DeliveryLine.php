<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\SalesOrderLine;

class DeliveryLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_id',
        'quotation_line_id',
        'sales_order_line_id',
        'item_id',
        'item_variant_id',
        'description',
        'unit',
        'qty',
        'qty_requested',
        'price_snapshot',
        'qty_backordered',
        'line_notes',
    ];

    protected $casts = [
        'qty'             => 'decimal:4',
        'qty_requested'   => 'decimal:4',
        'price_snapshot'  => 'decimal:2',
        'qty_backordered' => 'decimal:4',
    ];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class, 'item_variant_id');
    }

    public function quotationLine(): BelongsTo
    {
        return $this->belongsTo(QuotationLine::class);
    }

    public function salesOrderLine(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class);
    }

    public function setDescriptionAttribute($value)
    {
        $v = is_string($value) ? trim($value) : $value;
        $this->attributes['description'] = ($v === '' || $v === null) ? null : $v;
    }

}
