<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManufactureRecipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_item_id',
        // legacy (boleh dipertahankan sementara kalau masih ada data lama)
        'component_item_id',
        // NEW (utama)
        'component_variant_id',
        'qty_required',
        'unit_factor',
        'notes',
    ];

    public function parentItem()
    {
        return $this->belongsTo(Item::class, 'parent_item_id');
    }

    // legacy relation (opsional; keep sementara)
    public function componentItem()
    {
        return $this->belongsTo(Item::class, 'component_item_id');
    }

    // NEW: komponen berbasis SKU unik
    public function componentVariant()
    {
        return $this->belongsTo(ItemVariant::class, 'component_variant_id');
    }
}
