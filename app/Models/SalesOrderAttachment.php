<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderAttachment extends Model
{
    protected $fillable = [
        'sales_order_id','path','original_name','mime','size','uploaded_by_user_id'
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function salesOrder(): BelongsTo { return $this->belongsTo(SalesOrder::class); }
    public function uploader(): BelongsTo   { return $this->belongsTo(User::class, 'uploaded_by_user_id'); }
}
