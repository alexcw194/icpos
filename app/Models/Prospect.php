<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prospect extends Model
{
    public const SOURCE_GOOGLE_PLACES = 'google_places';

    public const STATUS_NEW = 'new';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_CONVERTED = 'converted';
    public const STATUS_IGNORED = 'ignored';

    protected $fillable = [
        'source',
        'place_id',
        'name',
        'formatted_address',
        'short_address',
        'city',
        'province',
        'country',
        'lat',
        'lng',
        'phone',
        'website',
        'google_maps_url',
        'primary_type',
        'types_json',
        'keyword_id',
        'grid_cell_id',
        'discovered_at',
        'last_seen_at',
        'status',
        'owner_user_id',
        'converted_customer_id',
        'raw_json',
    ];

    protected $casts = [
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'types_json' => 'array',
        'raw_json' => 'array',
        'discovered_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(LdKeyword::class, 'keyword_id');
    }

    public function gridCell(): BelongsTo
    {
        return $this->belongsTo(LdGridCell::class, 'grid_cell_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function convertedCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'converted_customer_id');
    }
}

