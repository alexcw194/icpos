<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItem extends Model
{
    protected $fillable = [
        'quotation_id','item_id','item_variant_id', // NEW: item_variant_id
        'name','description','qty','unit','unit_price','line_total'
    ];

    protected $casts = [
        'qty'        => 'decimal:2',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function quotation(): BelongsTo { return $this->belongsTo(Quotation::class); }
    public function item(): BelongsTo       { return $this->belongsTo(Item::class); }
    public function variant(): BelongsTo    { return $this->belongsTo(ItemVariant::class, 'item_variant_id'); }
}
