<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderLine extends Model
{
    protected $fillable = [
        'purchase_order_id','item_id','item_variant_id','item_name_snapshot','sku_snapshot',
        'qty_ordered','qty_received','uom','unit_price','line_total'
    ];

    public function po()    { return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id'); }
    public function item()  { return $this->belongsTo(Item::class); }
    public function variant(){ return $this->belongsTo(ItemVariant::class, 'item_variant_id'); }
}
