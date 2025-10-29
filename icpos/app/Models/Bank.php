<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// app/Models/Bank.php
class Bank extends Model
{
    protected $fillable = ['company_id','code','name','account_name','account_no','branch','is_active','notes'];
    protected $casts = ['is_active'=>'boolean'];

    public function company(){ return $this->belongsTo(\App\Models\Company::class); }

    // Label ringkas untuk dropdown
    public function getDisplayLabelAttribute(): string
    {
        $parts = [$this->code ?: $this->name, $this->account_no ? '— '.$this->account_no : null];
        return implode(' ', array_filter($parts));
    }

    // Heuristik sederhana: "NON" ⇒ Non-PPN
    public function getIsNonPpnAttribute(): bool
    {
        return str_contains(strtolower((string)$this->code), 'non');
    }

    // Scopes
    public function scopeActive($q){ return $q->where('is_active', true); }
    public function scopeForCompany($q, $companyId){ return $q->where('company_id', $companyId); }
}