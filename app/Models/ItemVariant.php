<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemVariant extends Model
{
    protected $fillable = [
        'item_id',
        'sku',
        'price',
        'stock',
        'attributes',
        'is_active',
        'barcode',
        'min_stock',
    ];

    protected $casts = [
        'price'      => 'decimal:2',
        'stock'      => 'integer',
        'attributes' => 'array',
        'is_active'  => 'boolean',
    ];

    // ========== RELATIONS ==========
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    // ========== ACCESSORS ==========
    /** Label tampilan varian, auto dari template item */
    protected function label(): Attribute
    {
        return Attribute::get(function () {
            $attr = is_array($this->attributes['attributes'] ?? null)
                ? $this->attributes['attributes'] : [];
            if ($this->relationLoaded('item') || $this->item) {
                return $this->item->renderVariantLabel($attr);
            }
            // fallback minimal
            return trim(($this->attributes['sku'] ?? '') ?: '');
        });
    }

    protected function name(): Attribute
    {
        return Attribute::get(function () {
            $label = (string) ($this->label ?? '');
            if ($label === '') {
                $label = (string) ($this->attributes['sku'] ?? '');
            }
            return $label !== '' ? $label : 'Variant #' . $this->id;
        });
    }

    /** Format harga versi Indonesia (Rp) tanpa prefix, cocok untuk tampilan */
    protected function priceId(): Attribute
    {
        return Attribute::get(function () {
            return number_format((float) $this->price, 2, ',', '.');
        });
    }

    public function color()
    {
        return $this->belongsTo(Color::class);
    }

    public function size()
    {
        return $this->belongsTo(Size::class);
    }

    public function getLabelAttribute(): string
    {
        $attrs = is_array($this->attributes) ? $this->attributes : [];

        $parts = [];

        $color  = trim((string)($attrs['color'] ?? ''));
        $size   = trim((string)($attrs['size'] ?? ''));
        $length = trim((string)($attrs['length'] ?? ''));

        if ($color !== '')  $parts[] = $color;
        if ($size !== '')   $parts[] = $size;
        if ($length !== '') $parts[] = $length;

        return $parts ? implode(' / ', $parts) : '-';
    }
}
