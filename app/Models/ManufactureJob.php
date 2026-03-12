<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManufactureJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_item_id',
        'qty_produced',
        'job_type',
        'json_components',
        'produced_by',
        'produced_at',
        'notes',
        'posted_at',
        'source_type',
        'source_id',
        'source_line_id',
        'is_auto',
        'reversed_at',
        'reversed_by',
        'reversal_notes',
    ];

    protected $casts = [
        'json_components' => 'array',
        'produced_at' => 'datetime',
        'posted_at' => 'datetime',
        'is_auto' => 'boolean',
        'reversed_at' => 'datetime',
    ];

    public function parentItem()
    {
        return $this->belongsTo(Item::class, 'parent_item_id');
    }

    public function producedBy()
    {
        return $this->belongsTo(User::class, 'produced_by');
    }

    public function sourceDelivery()
    {
        return $this->belongsTo(Delivery::class, 'source_id');
    }

    public function sourceLine()
    {
        return $this->belongsTo(DeliveryLine::class, 'source_line_id');
    }

    public function reversedBy()
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }
}
