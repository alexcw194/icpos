<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    protected $fillable = [
        'company_id','invoice_id','quotation_id',
        'number','date',
        'recipient','address','notes',
        'brand_snapshot',
    ];

    protected $casts = [
        'brand_snapshot' => 'array',
        'date' => 'date',
    ];

    public function company(){ return $this->belongsTo(Company::class); }
    public function invoice(){ return $this->belongsTo(Invoice::class); }
    public function quotation(){ return $this->belongsTo(Quotation::class); }
}
