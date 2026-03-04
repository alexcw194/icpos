<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LdScanLog extends Model
{
    protected $fillable = [
        'scan_run_id',
        'grid_cell_id',
        'keyword_id',
        'page_index',
        'request_url',
        'request_payload',
        'response_status',
        'results_count',
        'next_page_count',
        'error_message',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'results_count' => 'integer',
        'next_page_count' => 'integer',
        'page_index' => 'integer',
    ];

    public function scanRun(): BelongsTo
    {
        return $this->belongsTo(LdScanRun::class, 'scan_run_id');
    }

    public function gridCell(): BelongsTo
    {
        return $this->belongsTo(LdGridCell::class, 'grid_cell_id');
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(LdKeyword::class, 'keyword_id');
    }
}

