<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesCommissionNoteLine extends Model
{
    protected $fillable = [
        'sales_commission_note_id',
        'source_key',
        'invoice_id',
        'invoice_line_id',
        'sales_order_id',
        'sales_user_id',
        'item_id',
        'customer_id',
        'project_scope',
        'month',
        'revenue',
        'under_allocated',
        'commissionable_base',
        'rate_percent',
        'fee_amount',
        'invoice_number_snapshot',
        'sales_order_number_snapshot',
        'salesperson_name_snapshot',
        'item_name_snapshot',
        'customer_name_snapshot',
    ];

    protected $casts = [
        'month' => 'date',
        'revenue' => 'decimal:2',
        'under_allocated' => 'decimal:2',
        'commissionable_base' => 'decimal:2',
        'rate_percent' => 'decimal:2',
        'fee_amount' => 'decimal:2',
    ];

    public function note(): BelongsTo
    {
        return $this->belongsTo(SalesCommissionNote::class, 'sales_commission_note_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function salesUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_user_id');
    }
}
