<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaborCost extends Model
{
    protected $fillable = [
        'labor_id',
        'sub_contractor_id',
        'cost_amount',
        'is_active',
    ];

    protected $casts = [
        'cost_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function labor(): BelongsTo
    {
        return $this->belongsTo(Labor::class);
    }

    public function subContractor(): BelongsTo
    {
        return $this->belongsTo(SubContractor::class, 'sub_contractor_id');
    }
}
