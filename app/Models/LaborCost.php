<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaborCost extends Model
{
    protected $fillable = [
        'sub_contractor_id',
        'item_id',
        'item_variant_id',
        'context',
        'cost_amount',
    ];

    protected $casts = [
        'cost_amount' => 'decimal:2',
    ];

    public function subContractor(): BelongsTo
    {
        return $this->belongsTo(SubContractor::class, 'sub_contractor_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
