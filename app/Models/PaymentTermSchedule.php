<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentTermSchedule extends Model
{
    protected $fillable = [
        'payment_term_id',
        'sequence',
        'portion_type',
        'portion_value',
        'due_trigger',
        'offset_days',
        'specific_day',
        'notes',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'portion_value' => 'decimal:4',
        'offset_days' => 'integer',
        'specific_day' => 'integer',
    ];

    public function term()
    {
        return $this->belongsTo(TermOfPayment::class, 'payment_term_id');
    }
}
