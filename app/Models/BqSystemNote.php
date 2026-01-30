<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BqSystemNote extends Model
{
    protected $fillable = [
        'system_key',
        'notes_template',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
