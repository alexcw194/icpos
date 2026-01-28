<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderVariation extends Model
{
    protected $fillable = [
        'sales_order_id',
        'vo_number',
        'vo_date',
        'reason',
        'delta_amount',
        'status',
        'created_by',
    ];

    protected $casts = [
        'vo_date' => 'date',
        'delta_amount' => 'decimal:2',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
