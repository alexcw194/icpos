<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockSummary extends Model
{
    protected $fillable = [
        'company_id','warehouse_id','item_id','variant_id','qty_balance','uom',
    ];
    public function company(){ return $this->belongsTo(Company::class); }
    public function warehouse(){ return $this->belongsTo(Warehouse::class); }
    public function item(){ return $this->belongsTo(Item::class); }
    public function variant(){ return $this->belongsTo(ItemVariant::class, 'variant_id'); }
}
