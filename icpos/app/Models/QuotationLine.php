<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationLine extends Model
{
    protected $fillable = [
        'quotation_id',
        'name','description',
        'qty','unit','unit_price',
        'discount_type','discount_value','discount_amount',
        'line_subtotal','line_total',

        // NEW: link ke master item/varian (opsional)
        'item_id','item_variant_id',
    ];

    protected $casts = [
        'qty'             => 'decimal:4',
        'unit_price'      => 'decimal:2',
        'discount_value'  => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'line_subtotal'   => 'decimal:2',
        'line_total'      => 'decimal:2',
        'item_id'         => 'integer',
        'item_variant_id' => 'integer',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    // Kalau model ItemVariant belum ada, abaikan relasi ini dulu/komentari.
    public function itemVariant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\ItemVariant::class);
    }
}
