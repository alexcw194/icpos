<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectItemLaborRate extends Model
{
    protected $fillable = [
        'project_item_id',
        'labor_unit_cost',
        'notes',
        'updated_by',
    ];

    protected $casts = [
        'labor_unit_cost' => 'decimal:2',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'project_item_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
