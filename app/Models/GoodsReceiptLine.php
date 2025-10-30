<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsReceiptLine extends Model
{
    protected $fillable = [
        'goods_receipt_id','item_id','item_variant_id','item_name_snapshot','sku_snapshot',
        'qty_received','uom','unit_cost','line_total'
    ];

    public function gr()     { return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id'); }
    public function item()   { return $this->belongsTo(Item::class); }
    public function variant(){ return $this->belongsTo(ItemVariant::class, 'item_variant_id'); }
}
