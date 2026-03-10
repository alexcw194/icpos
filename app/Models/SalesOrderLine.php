<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderLine extends Model
{
    protected $fillable = [
        'sales_order_id','position',
        'name','po_item_name','description','unit',
        'qty_ordered','unit_price',
        'material_total','labor_total',
        'discount_type','discount_value','discount_amount',
        'line_subtotal','line_total',
        // NEW
        'item_id','item_variant_id',
        'baseline_project_quotation_line_id',
        'baseline_name',
        'baseline_description',
        'baseline_item_id',
        'baseline_item_variant_id',
        'baseline_qty',
        'baseline_unit',
        'baseline_unit_price',
        'baseline_material_total',
        'baseline_labor_total',
        'baseline_line_total',
    ];

    protected $casts = [
        'qty_ordered'     => 'decimal:2',
        'unit_price'      => 'decimal:2',
        'material_total'  => 'decimal:2',
        'labor_total'     => 'decimal:2',
        'discount_value'  => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'line_subtotal'   => 'decimal:2',
        'line_total'      => 'decimal:2',
        // (opsional) bantu casting id
        'item_id'         => 'integer',
        'item_variant_id' => 'integer',
        'baseline_project_quotation_line_id' => 'integer',
        'baseline_item_id' => 'integer',
        'baseline_item_variant_id' => 'integer',
        'baseline_qty' => 'decimal:4',
        'baseline_unit_price' => 'decimal:2',
        'baseline_material_total' => 'decimal:2',
        'baseline_labor_total' => 'decimal:2',
        'baseline_line_total' => 'decimal:2',
    ];

    public function salesOrder(): BelongsTo { return $this->belongsTo(SalesOrder::class); }

    // NEW
    public function item(): BelongsTo { return $this->belongsTo(Item::class); }
    public function variant(): BelongsTo { return $this->belongsTo(ItemVariant::class, 'item_variant_id'); }
    public function baselineItem(): BelongsTo { return $this->belongsTo(Item::class, 'baseline_item_id'); }
    public function baselineVariant(): BelongsTo { return $this->belongsTo(ItemVariant::class, 'baseline_item_variant_id'); }
}
