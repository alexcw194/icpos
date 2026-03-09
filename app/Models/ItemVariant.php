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
        'last_cost',
        'last_cost_at',
        'avg_cost',
        'default_cost',
        'variant_key',
    ];

    protected $casts = [
        'price'      => 'decimal:2',
        'stock'      => 'integer',
        'attributes' => 'array',
        'is_active'  => 'boolean',
        'last_cost'    => 'decimal:2',
        'last_cost_at' => 'datetime',
        'avg_cost'     => 'decimal:2',
        'default_cost' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (ItemVariant $variant) {
            $attributes = $variant->getAttribute('attributes');
            $variant->variant_key = static::buildVariantKey(is_array($attributes) ? $attributes : []);
        });
    }

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
            return $this->resolveDisplayLabel();
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
        return $this->resolveDisplayLabel();
    }

    public function setPriceAttribute($value): void
    {
        $s = is_null($value) ? '0' : (string) $value;
        $s = preg_replace('/[^\d,.\-]/', '', $s);

        $hasComma = str_contains($s, ',');
        $hasDot   = str_contains($s, '.');

        // ID format: 1.234.567,89
        if ($hasComma && $hasDot) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        }
        // ID format: 1234,56
        elseif ($hasComma) {
            $s = str_replace(',', '.', $s);
        }
        // EN format: 1234.56 -> biarkan

        $this->attributes['price'] = (float) $s;
    }

    public function setStockAttribute($value): void
    {
        $n = (int) preg_replace('/[^\d\-]/', '', (string) ($value ?? 0));
        $this->attributes['stock'] = max(0, $n);
    }

    public function setSkuAttribute($value): void
    {
        $sku = trim((string) $value);
        $this->attributes['sku'] = $sku === '' ? null : mb_strtoupper($sku, 'UTF-8');
    }

    public function getMarginAttribute(): ?string
    {
        $sell = $this->price;
        $cost = $this->avg_cost ?? $this->last_cost ?? $this->default_cost;

        if ($sell === null || $cost === null) return null;
        return number_format(((float)$sell - (float)$cost), 2, '.', '');
    }

    private function resolveDisplayLabel(): string
    {
        $attr = $this->getAttribute('attributes');
        $attr = is_array($attr) ? $attr : [];

        if ($this->relationLoaded('item') && $this->item) {
            return $this->item->renderVariantDisplayName($attr, $this->sku);
        }

        if ($this->item) {
            return $this->item->renderVariantDisplayName($attr, $this->sku);
        }

        $sku = trim((string) ($this->sku ?? ''));
        if ($sku !== '') {
            return $sku;
        }

        return 'Variant #' . $this->id;
    }

    public static function buildVariantKey(array $attributes): string
    {
        $normalized = static::normalizeVariantAttributes($attributes);
        if (empty($normalized)) {
            return '__BASE__';
        }

        $json = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return 'V1:' . sha1((string) $json);
    }

    public static function normalizeVariantAttributes(array $attributes): array
    {
        $normalized = [];

        foreach ($attributes as $key => $value) {
            $key = strtolower(trim((string) $key));
            if ($key === '') {
                continue;
            }

            if (is_array($value) || is_object($value)) {
                continue;
            }

            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }

            $normalized[$key] = mb_strtolower($value, 'UTF-8');
        }

        ksort($normalized);
        return $normalized;
    }
}
