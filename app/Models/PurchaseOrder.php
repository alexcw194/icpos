<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'company_id','supplier_id','warehouse_id','number','order_date','status',
        'subtotal','discount_amount','tax_percent','tax_amount','total','notes',
        'approved_at','approved_by'
    ];

    public function lines()     { return $this->hasMany(PurchaseOrderLine::class); }
    public function company()   { return $this->belongsTo(Company::class); }
    public function supplier()  { return $this->belongsTo(Customer::class, 'supplier_id'); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }

    public function markReceivedStats(): void {
        $ordered  = $this->lines()->sum('qty_ordered');
        $received = $this->lines()->sum('qty_received');
        $this->status = $received <= 0 ? $this->status
                     : ($received < $ordered ? 'partially_received' : 'fully_received');
        $this->save();
    }
}
