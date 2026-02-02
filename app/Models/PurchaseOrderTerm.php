<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderTerm extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'seq',
        'top_code',
        'percent',
        'note',
        'due_trigger',
        'offset_days',
        'day_of_month',
        'status',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
