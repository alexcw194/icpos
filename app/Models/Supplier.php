<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
        'notes',
        'default_billing_terms',
        'is_active',
    ];

    protected $casts = [
        'default_billing_terms' => 'array',
        'is_active' => 'boolean',
    ];
}
