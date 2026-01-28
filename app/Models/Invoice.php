<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'company_id','customer_id','quotation_id','sales_order_id','so_billing_term_id',
        'number','date','status',
        'invoice_kind','payment_schedule_seq','payment_schedule_meta',
        'subtotal','discount','tax_percent','tax_amount','total',
        'currency','brand_snapshot', 'due_date', 'posted_at', 'receipt_path',
        'status','paid_at','paid_amount','paid_bank','paid_ref','payment_notes',
        'notes','created_by',
    ];

    protected $casts = [
        'brand_snapshot' => 'array',
        'date' => 'date',
        'due_date'  => 'date',
        'posted_at' => 'datetime',
        'paid_at'=>'datetime',
        'payment_schedule_meta' => 'array',
    ];

    public function company(){ return $this->belongsTo(Company::class); }
    public function customer(){ return $this->belongsTo(Customer::class); }
    public function quotation(){ return $this->belongsTo(Quotation::class); }
    public function salesOrder(){ return $this->belongsTo(SalesOrder::class); }
    public function billingTerm(){ return $this->belongsTo(SalesOrderBillingTerm::class, 'so_billing_term_id'); }
    public function lines(){ return $this->hasMany(\App\Models\InvoiceLine::class); }
    public function delivery()
    {
        return $this->hasOne(\App\Models\Delivery::class);
    }
}
