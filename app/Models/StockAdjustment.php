<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockAdjustment extends Model
{
    protected $fillable = [
        'company_id','warehouse_id','item_id','variant_id',
        'qty_adjustment','reason','created_by'
    ];

    public function item() { return $this->belongsTo(Item::class); }
    public function variant() { return $this->belongsTo(ItemVariant::class, 'variant_id'); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function company() { return $this->belongsTo(Company::class); }
}
