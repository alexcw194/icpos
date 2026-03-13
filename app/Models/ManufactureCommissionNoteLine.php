<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManufactureCommissionNoteLine extends Model
{
    protected $fillable = [
        'manufacture_commission_note_id',
        'category',
        'item_id',
        'customer_id',
        'month',
        'source_key',
        'qty',
        'fee_rate',
        'fee_amount',
        'item_name_snapshot',
        'customer_name_snapshot',
    ];

    protected $casts = [
        'month' => 'date',
        'qty' => 'decimal:4',
        'fee_rate' => 'decimal:2',
        'fee_amount' => 'decimal:2',
    ];

    public function note(): BelongsTo
    {
        return $this->belongsTo(ManufactureCommissionNote::class, 'manufacture_commission_note_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
