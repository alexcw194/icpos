<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProspectAnalysis extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    public const AI_STATUS_NOT_RUN = 'not_run';
    public const AI_STATUS_SUCCESS = 'success';
    public const AI_STATUS_FAILED = 'failed';

    public const ADDRESS_CLEAR = 'clear';
    public const ADDRESS_PARTIAL = 'partial';
    public const ADDRESS_MISSING = 'missing';

    protected $fillable = [
        'prospect_id',
        'requested_by_user_id',
        'status',
        'ai_status',
        'ai_provider',
        'ai_model',
        'ai_industry_label',
        'ai_sub_industry',
        'ai_business_output',
        'ai_employee_range',
        'ai_hotel_star',
        'ai_confidence',
        'ai_payload_json',
        'ai_error_message',
        'website_url',
        'website_http_status',
        'website_reachable',
        'pages_crawled',
        'crawled_urls_json',
        'emails_json',
        'phones_json',
        'linkedin_company_url',
        'linkedin_people_json',
        'business_type',
        'business_signals_json',
        'address_clarity',
        'checklist_json',
        'score',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'website_reachable' => 'boolean',
        'pages_crawled' => 'integer',
        'ai_hotel_star' => 'integer',
        'ai_confidence' => 'decimal:2',
        'ai_payload_json' => 'array',
        'crawled_urls_json' => 'array',
        'emails_json' => 'array',
        'phones_json' => 'array',
        'linkedin_people_json' => 'array',
        'business_signals_json' => 'array',
        'checklist_json' => 'array',
        'score' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class, 'prospect_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }
}
