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
        'item_variant_id',
        'item_label',
        'line_type',
        'catalog_id',
        'percent_value',
        'percent_basis',
        'computed_amount',
        'cost_bucket',
        'qty',
        'unit',
        'unit_price',
        'material_total',
        'labor_total',
        'labor_source',
        'labor_unit_cost_snapshot',
        'labor_override_reason',
        'labor_cost_amount',
        'labor_margin_amount',
        'labor_cost_missing',
        'line_total',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'material_total' => 'decimal:2',
        'labor_total' => 'decimal:2',
        'labor_unit_cost_snapshot' => 'decimal:2',
        'labor_cost_amount' => 'decimal:2',
        'labor_margin_amount' => 'decimal:2',
        'labor_cost_missing' => 'boolean',
        'line_total' => 'decimal:2',
        'percent_value' => 'decimal:4',
        'computed_amount' => 'decimal:2',
        'item_variant_id' => 'integer',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(ProjectQuotationSection::class, 'section_id');
    }
}
