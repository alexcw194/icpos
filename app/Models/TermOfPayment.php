<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TermOfPayment extends Model
{
    public const ALLOWED_CODES = [
        'DP', 'T1', 'T2', 'T3', 'T4', 'T5', 'FINISH', 'R1', 'R2', 'R3',
        'DP50_BALANCE_ON_DELIVERY', 'NET14', 'NET30', 'NET45', 'EOM20',
    ];

    protected $fillable = [
        'code',
        'description',
        'is_active',
        'applicable_to',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'applicable_to' => 'array',
    ];

    public function schedules()
    {
        return $this->hasMany(PaymentTermSchedule::class, 'payment_term_id')
            ->orderBy('sequence');
    }
}
