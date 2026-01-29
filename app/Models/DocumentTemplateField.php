<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentTemplateField extends Model
{
    protected $fillable = [
        'document_template_id',
        'field_key',
        'label',
        'field_type',
        'required',
        'sort_order',
        'options',
    ];

    protected $casts = [
        'required' => 'boolean',
        'options' => 'array',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'document_template_id');
    }
}
