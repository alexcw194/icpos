<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = [
        'name', 'alias',
        'is_taxable', 'default_tax_percent',
        'quotation_prefix', 'invoice_prefix', 'delivery_prefix',
        'address', 'tax_id', 'email', 'phone',
        'bank_name', 'bank_account_name', 'bank_account_no', 'bank_account_branch',
        'logo_path',
        'is_default',
        'require_npwp_on_so',
        // NEW: default masa berlaku quotation (hari)
        'default_valid_days',
    ];

    protected $casts = [
        'is_taxable'          => 'boolean',
        'is_default'          => 'boolean',
        'default_tax_percent' => 'decimal:2',
        'require_npwp_on_so'  => 'boolean',
        // NEW
        'default_valid_days'  => 'integer',
    ];

    public function banks()
    {
        return $this->hasMany(\App\Models\Bank::class)->orderBy('code')->orderBy('name');
    }
}
