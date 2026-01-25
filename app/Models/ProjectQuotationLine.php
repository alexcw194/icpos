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
        'source_type',
        'item_id',
        'item_label',
        'line_type',
        'source_template_id',
        'source_template_line_id',
        'percent_value',
        'basis_type',
        'computed_amount',
        'editable_price',
        'editable_percent',
        'can_remove',
        'qty',
        'unit',
        'unit_price',
        'material_total',
        'labor_total',
        'labor_source',
        'labor_unit_cost_snapshot',
        'labor_override_reason',
        'line_total',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'material_total' => 'decimal:2',
        'labor_total' => 'decimal:2',
        'labor_unit_cost_snapshot' => 'decimal:2',
        'line_total' => 'decimal:2',
        'percent_value' => 'decimal:4',
        'computed_amount' => 'decimal:2',
        'editable_price' => 'boolean',
        'editable_percent' => 'boolean',
        'can_remove' => 'boolean',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(ProjectQuotationSection::class, 'section_id');
    }
}
