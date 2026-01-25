<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BqLineTemplate extends Model
{
    protected $fillable = [
        'name',
        'description',
        'is_active',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(BqLineTemplateLine::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
