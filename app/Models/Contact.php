<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\ContactTitle;
use App\Models\ContactPosition;

class Contact extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'first_name',
        'last_name',
        'contact_title_id',
        'contact_position_id',
        'title_snapshot',
        'position_snapshot',
        'title',
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
    protected $appends = ['name', 'full_name', 'title_label', 'position_label'];

    public function titleMaster(): BelongsTo
    {
        return $this->belongsTo(ContactTitle::class, 'contact_title_id');
    }

    public function positionMaster(): BelongsTo
    {
        return $this->belongsTo(ContactPosition::class, 'contact_position_id');
    }

    public function getNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }

    public function getFullNameAttribute(): string
    {
        $title = $this->getTitleLabelAttribute();
        $name = $this->getNameAttribute();
        return trim($title.' '.$name);
    }

    public function getTitleLabelAttribute(): string
    {
        return trim((string) ($this->title_snapshot ?? ($this->titleMaster->name ?? ($this->title ?? ''))));
    }

    public function getPositionLabelAttribute(): string
    {
        return trim((string) ($this->position_snapshot ?? ($this->positionMaster->name ?? ($this->position ?? ''))));
    }
}
