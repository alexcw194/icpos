<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    protected $fillable = ['code','name','account_name','account_no','is_active'];
    protected $casts = ['is_active' => 'bool'];

    public function scopeActive($q){ return $q->where('is_active', true); }

    public function label(): string
    {
        $parts = array_filter([$this->code, $this->name, $this->account_no]);
        return implode(' • ', $parts);
    }
}
