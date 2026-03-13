<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesCommissionNote extends Model
{
    protected $fillable = [
        'number',
        'month',
        'sales_user_id',
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
        return $this->hasMany(SalesCommissionNoteLine::class)->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function salesUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_user_id');
    }
}
