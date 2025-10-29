<?php
namespace App\Models;


use Illuminate\Database\Eloquent\Model;


class InvoiceLine extends Model
{
protected $fillable = [
'invoice_id','quotation_id','quotation_line_id','sales_order_id','sales_order_line_id',
'delivery_id','delivery_line_id','item_id','item_variant_id','description','unit','qty',
'unit_price','discount_amount','line_subtotal','line_total','snapshot_json'
];


protected $casts = [
'qty' => 'decimal:4',
'unit_price' => 'decimal:2',
'discount_amount' => 'decimal:2',
'line_subtotal' => 'decimal:2',
'line_total' => 'decimal:2',
'snapshot_json' => 'array',
];


public function invoice(){ return $this->belongsTo(Invoice::class); }
}