<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class SalesOrderAttachment extends Model
{
    protected $fillable = [
        'sales_order_id','draft_token','path','original_name','mime','size','uploaded_by_user_id'
    ];

    protected $appends = ['url'];

    public function getUrlAttribute(): string
    {
        return $this->path ? Storage::disk('public')->url($this->path) : '#';
    }

    public function salesOrder() { return $this->belongsTo(SalesOrder::class); }
}