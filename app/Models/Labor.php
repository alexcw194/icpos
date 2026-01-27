<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Labor extends Model
{
    protected $fillable = [
        'code',
        'name',
        'unit',
        'is_active',
        'default_sub_contractor_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function defaultSubContractor(): BelongsTo
    {
        return $this->belongsTo(SubContractor::class, 'default_sub_contractor_id');
    }

    public function costs(): HasMany
    {
        return $this->hasMany(LaborCost::class);
    }
}
