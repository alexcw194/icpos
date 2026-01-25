<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BqLineTemplateLine extends Model
{
    protected $fillable = [
        'bq_line_template_id',
        'sort_order',
        'type',
        'label',
        'default_qty',
        'default_unit',
        'default_unit_price',
        'percent_value',
        'basis_type',
        'applies_to',
        'editable_price',
        'editable_percent',
        'can_remove',
    ];

    protected $casts = [
        'default_qty' => 'decimal:2',
        'default_unit_price' => 'decimal:2',
        'percent_value' => 'decimal:4',
        'editable_price' => 'boolean',
        'editable_percent' => 'boolean',
        'can_remove' => 'boolean',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(BqLineTemplate::class, 'bq_line_template_id');
    }
}
