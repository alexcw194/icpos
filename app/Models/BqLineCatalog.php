<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BqLineCatalog extends Model
{
    protected $fillable = [
        'name',
        'type',
        'default_qty',
        'default_unit',
        'default_unit_price',
        'default_percent',
        'percent_basis',
        'cost_bucket',
        'is_active',
        'description',
    ];

    protected $casts = [
        'default_qty' => 'decimal:2',
        'default_unit_price' => 'decimal:2',
        'default_percent' => 'decimal:4',
        'is_active' => 'boolean',
    ];
}
