<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LdKeyword extends Model
{
    protected $fillable = [
        'keyword',
        'category_label',
        'is_active',
        'priority',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    public function prospects(): HasMany
    {
        return $this->hasMany(Prospect::class, 'keyword_id');
    }

    public function scanLogs(): HasMany
    {
        return $this->hasMany(LdScanLog::class, 'keyword_id');
    }
}

