<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectQuotationLine extends Model
{
    protected $fillable = [
        'section_id',
        'line_no',
        'description',
        'qty',
        'unit',
        'unit_price',
        'material_total',
        'labor_total',
        'line_total',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'material_total' => 'decimal:2',
        'labor_total' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(ProjectQuotationSection::class, 'section_id');
    }
}
