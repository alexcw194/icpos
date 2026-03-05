<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProspectApolloEnrichment extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    public const MATCHED_BY_DOMAIN = 'domain';
    public const MATCHED_BY_NAME_LOCATION = 'name_location';
    public const MATCHED_BY_NONE = 'none';

    protected $fillable = [
        'prospect_id',
        'requested_by_user_id',
        'status',
        'seed_website',
        'seed_domain',
        'matched_by',
        'apollo_org_id',
        'apollo_org_name',
        'apollo_domain',
        'apollo_website_url',
        'apollo_linkedin_url',
        'apollo_industry',
        'apollo_sub_industry',
        'apollo_business_output',
        'apollo_employee_range',
        'apollo_city',
        'apollo_state',
        'apollo_country',
        'apollo_people_json',
        'apollo_payload_json',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'apollo_people_json' => 'array',
        'apollo_payload_json' => 'array',
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
