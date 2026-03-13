<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManufactureCommissionNote extends Model
{
    protected $fillable = [
        'number',
        'month',
        'status',
        'note_date',
        'paid_at',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'month' => 'date',
        'note_date' => 'date',
        'paid_at' => 'date',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(ManufactureCommissionNoteLine::class)->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
