<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectQuotationPaymentTerm extends Model
{
    protected $fillable = [
        'project_quotation_id',
        'code',
        'label',
        'percent',
        'due_trigger',
        'offset_days',
        'day_of_month',
        'sequence',
        'trigger_note',
    ];

    protected $casts = [
        'percent' => 'decimal:2',
        'offset_days' => 'integer',
        'day_of_month' => 'integer',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(ProjectQuotation::class, 'project_quotation_id');
    }
}
