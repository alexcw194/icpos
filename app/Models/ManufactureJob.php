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
    ];

    protected $casts = [
        'json_components' => 'array',
        'produced_at' => 'datetime',
    ];

    public function parentItem()
    {
        return $this->belongsTo(Item::class, 'parent_item_id');
    }

    public function producedBy()
    {
        return $this->belongsTo(User::class, 'produced_by');
    }
}
