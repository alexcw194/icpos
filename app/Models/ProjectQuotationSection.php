<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectQuotationSection extends Model
{
    protected $fillable = [
        'project_quotation_id',
        'name',
        'sort_order',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(ProjectQuotation::class, 'project_quotation_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ProjectQuotationLine::class, 'section_id');
    }
}
