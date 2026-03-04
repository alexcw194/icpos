<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LdGridCell extends Model
{
    protected $fillable = [
        'name',
        'center_lat',
        'center_lng',
        'radius_m',
        'region_code',
        'city',
        'province',
        'is_active',
        'last_scanned_at',
    ];

    protected $casts = [
        'center_lat' => 'decimal:7',
        'center_lng' => 'decimal:7',
        'radius_m' => 'integer',
        'is_active' => 'boolean',
        'last_scanned_at' => 'datetime',
    ];

    public function prospects(): HasMany
    {
        return $this->hasMany(Prospect::class, 'grid_cell_id');
    }

    public function scanLogs(): HasMany
    {
        return $this->hasMany(LdScanLog::class, 'grid_cell_id');
    }
}

