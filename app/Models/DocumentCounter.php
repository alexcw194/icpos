<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentCounter extends Model
{
    protected $table = 'document_counters';

    protected $fillable = [
        'company_id',
        'doc_type',
        'year',
        'last_seq',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'year'       => 'integer',
        'last_seq'   => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
