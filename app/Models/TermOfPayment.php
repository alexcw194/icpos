<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TermOfPayment extends Model
{
    public const ALLOWED_CODES = [
        'DP', 'T1', 'T2', 'T3', 'T4', 'T5', 'FINISH', 'R1', 'R2', 'R3',
    ];

    protected $fillable = [
        'code',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
