<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsReceipt extends Model
{
    protected $fillable = [
        'company_id','warehouse_id','purchase_order_id','number','gr_date','received_at','status','notes','posted_at','posted_by'
    ];

    protected $casts = [
        'gr_date' => 'date',
        'received_at' => 'datetime',
        'posted_at' => 'datetime',
    ];

    public function lines()     { return $this->hasMany(GoodsReceiptLine::class); }
    public function company()   { return $this->belongsTo(Company::class); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function po()        { return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id'); }
}
