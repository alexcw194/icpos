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
        'sequence',
        'trigger_note',
    ];

    protected $casts = [
        'percent' => 'decimal:2',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(ProjectQuotation::class, 'project_quotation_id');
    }
}
