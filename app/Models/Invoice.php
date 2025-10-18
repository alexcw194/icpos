<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'company_id','customer_id','quotation_id',
        'number','date','status',
        'subtotal','discount','tax_percent','tax_amount','total',
        'currency','brand_snapshot',
    ];

    protected $casts = [
        'brand_snapshot' => 'array',
        'date' => 'date',
    ];

    public function company(){ return $this->belongsTo(Company::class); }
    public function customer(){ return $this->belongsTo(Customer::class); }
    public function quotation(){ return $this->belongsTo(Quotation::class); }
    public function lines(){ return $this->hasMany(\App\Models\InvoiceLine::class); }
    public function delivery()
    {
        return $this->hasOne(\App\Models\Delivery::class);
    }
}
