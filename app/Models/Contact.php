<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contact extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'title',
        'first_name',
        'last_name',
        'position',
        'email',
        'phone',
        'notes',
    ];

    /** ---------------------------
     *  RELATIONS
     *  ------------------------- */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** ---------------------------
     *  ACCESSORS
     *  ------------------------- */
    protected $appends = ['name'];

    public function getNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }

    public function getFullNameAttribute(): string
    {
        $title = trim((string) ($this->title ?? ''));
        $name = $this->getNameAttribute();
        return trim($title.' '.$name);
    }
}
