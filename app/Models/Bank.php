<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// app/Models/Bank.php
class Bank extends Model {
    protected $fillable = ['name','account_name','account_no','branch','is_active','notes','tax_scope','account_alias'];

    public function getDisplayLabelAttribute(): string
    {
        $parts = [
          $this->account_alias ?: $this->name,                          // "BCA PPN" atau "BCA"
          $this->tax_scope ? "({$this->tax_scope})" : null,            // (PPN)/(NON_PPN)
          $this->account_no ? "— {$this->account_no}" : null,          // — 1234567890
        ];
        return implode(' ', array_filter($parts));
    }
}

