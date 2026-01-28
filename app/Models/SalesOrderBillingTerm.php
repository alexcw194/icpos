<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderBillingTerm extends Model
{
    protected $table = 'so_billing_terms';

    protected $fillable = [
        'sales_order_id',
        'seq',
        'top_code',
        'percent',
        'due_trigger',
        'offset_days',
        'day_of_month',
        'note',
        'status',
        'invoice_id',
    ];

    protected $casts = [
        'percent' => 'decimal:2',
        'offset_days' => 'integer',
        'day_of_month' => 'integer',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(TermOfPayment::class, 'top_code', 'code');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
