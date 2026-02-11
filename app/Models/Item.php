<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'sku',
        'description',
        'price',
        'default_cost',
        'last_cost',
        'last_cost_at',
        'stock',
        'unit_id',
        'brand_id',
        'list_type',
        'item_type',
        'parent_id',
        'family_code',
        'sellable',
        'purchasable',
        'default_roll_length',
        'length_per_piece',
        'attributes',
        'size_id',
        'color_id',

        // NEW (V2 variants)
        'variant_type',      // none | color | size | length | color_size
        'variant_options',   // json
        'name_template',     // optional override template
    ];

    protected $casts = [
        'price'               => 'decimal:2',
        'default_cost'        => 'decimal:2',
        'last_cost'           => 'decimal:2',
        'last_cost_at'        => 'datetime',
        'stock'               => 'integer',
        'sellable'            => 'boolean',
        'purchasable'         => 'boolean',
        'default_roll_length' => 'decimal:2',
        'length_per_piece'    => 'decimal:2',
        'attributes'          => 'array',
        'variant_options'     => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Item $item) {
            $item->ensureSkuPresent();
        });

        static::updating(function (Item $item) {
            $item->ensureSkuPresent();
        });
    }

    // ========== RELATIONS ==========
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function unit(): BelongsTo    { return $this->belongsTo(Unit::class); }
    public function brand(): BelongsTo   { return $this->belongsTo(Brand::class); }

    /** NEW: daftar varian untuk item ini */
    public function variants(): HasMany
    {
        return $this->hasMany(ItemVariant::class)->orderBy('id');
    }

    // ========== ACCESSORS & MUTATORS ==========
    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn($v) => is_string($v) ? trim($v) : $v
        );
    }

    protected function sku(): Attribute
    {
        return Attribute::make(
            set: fn($v) => is_string($v) ? strtoupper(trim($v)) : $v
        );
    }

    protected function description(): Attribute
    {
        return Attribute::make(
            set: fn($v) => is_string($v) ? trim($v) : $v
        );
    }

    public function getPriceIdAttribute(): string
    {
        return number_format((float) $this->price, 2, ',', '.');
    }

    public function setPriceAttribute($value): void
    {
        if (is_string($value)) {
            $v = preg_replace('/[^0-9,.\-]/', '', $value) ?? '';
            if (str_contains($v, ',')) {
                $v = str_replace('.', '', $v);
                $v = str_replace(',', '.', $v);
            }
            $value = is_numeric($v) ? (float) $v : 0;
        }
        $this->attributes['price'] = $value;
    }

    public function setStockAttribute($value): void
    {
        $this->attributes['stock'] = is_numeric($value) ? (int) $value : 0;
    }

    // Normalisasi kecil untuk variant_type
    protected function variantType(): Attribute
    {
        return Attribute::make(
            get: fn ($v) => $v ?: 'none',
            set: fn ($v) => in_array($v, ['none','color','size','length','color_size'], true) ? $v : 'none'
        );
    }

    // ========== HELPERS: TEMPLATE & LABEL ==========

    /** Template default berdasar variant_type */
    public function defaultNameTemplate(): string
    {
        return match ($this->variant_type) {
            'color'      => '{name} — {color}',
            'size'       => '{name} — {size}',
            'length'     => '{name} {length}m',
            'color_size' => '{name} — {color} / {size}',
            default      => '{name}',
        };
    }

    /**
     * Render label varian dari attributes (e.g. ['color'=>'Blue','size'=>'M'])
     * - Gunakan $this->name_template kalau ada; kalau tidak pakai defaultNameTemplate()
     * - Token yang dikenali: {name}, {color}, {size}, {length}
     */
    public function renderVariantLabel(array $attributes = [], ?string $overrideTemplate = null): string
    {
        $template = $overrideTemplate ?: ($this->name_template ?: $this->defaultNameTemplate());

        // siapkan map token
        $tokens = [
            '{name}'   => (string) $this->name,
            '{color}'  => (string) ($attributes['color']  ?? ''),
            '{size}'   => (string) ($attributes['size']   ?? ''),
            '{length}' => (string) ($attributes['length'] ?? ''),
        ];

        $label = strtr($template, $tokens);

        // rapikan spasi/strip berlebih jika atribut kosong
        $label = preg_replace('/\s+—\s+$/u', '', $label);           // buang " — " di ujung
        $label = preg_replace('/\s+\/\s+$/u', '', $label);           // buang " / " di ujung
        $label = preg_replace('/\s{2,}/u', ' ', $label);             // multiple spaces
        $label = trim($label, " \t\n\r\0\x0B-—/");                   // trim karakter pemisah di ujung
        return $label !== '' ? $label : (string) $this->name;
    }

    // ========== SCOPES ==========
    public function scopeForCompany($query, $companyId = null)
    {
        $companyId ??= auth()->user()?->company_id;
        return $companyId ? $query->where('company_id', $companyId) : $query;
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('name');
    }

    public function scopeKeyword($query, ?string $q)
    {
        $q = trim((string) $q);
        if ($q === '') return $query;

        return $query->where(function ($w) use ($q) {
            $w->where('name', 'like', "%{$q}%")
              ->orWhere('sku', 'like', "%{$q}%");
        });
    }

    public function scopeInUnit($query, $unitId)
    {
        return $unitId ? $query->where('unit_id', $unitId) : $query;
    }

    public function scopeInBrand($query, $brandId)
    {
        return $brandId ? $query->where('brand_id', $brandId) : $query;
    }

    public function scopeRetail($query)
    {
        return $query->where('list_type', 'retail');
    }

    public function scopeProject($query)
    {
        return $query->where('list_type', 'project');
    }

    public function size(){ return $this->belongsTo(Size::class); }
    public function color(){ return $this->belongsTo(Color::class); }
     public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** NEW: filter berdasarkan tipe varian */
    public function scopeVariantType($query, ?string $type)
    {
        if (!$type) return $query;
        return $query->where('variant_type', $type);
    }

    private function ensureSkuPresent(): void
    {
        $sku = $this->getAttribute('sku');

        if (self::isSkuBlank($sku)) {
            $this->sku = static::generateUniqueSku();
        }
    }

    private static function isSkuBlank($value): bool
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        return $value === null || $value === '';
    }

    private static function generateUniqueSku(): string
    {
        $config = config('icpos.sku', []);
        $prefix = strtoupper((string) Arr::get($config, 'prefix', 'ITM'));
        $dateFormat = (string) Arr::get($config, 'date', 'Ymd');
        $width = (int) Arr::get($config, 'width', 4);
        if ($width < 1) {
            $width = 4;
        }

        $datePart = now()->format($dateFormat);
        $base = $prefix . '-' . $datePart . '-';

        $sequence = max(1, static::determineNextSkuSequence($base));
        $maxAttempts = 50;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $candidate = $base . str_pad((string) $sequence, $width, '0', STR_PAD_LEFT);

            if (! static::query()->where('sku', $candidate)->exists()) {
                return $candidate;
            }

            $sequence++;
            $attempt++;
        }

        throw new RuntimeException('Unable to generate unique SKU after multiple attempts.');
    }

    private static function determineNextSkuSequence(string $base): int
    {
        $query = static::query()
            ->where('sku', 'like', $base . '%')
            ->orderByDesc('sku');

        if (DB::transactionLevel() > 0) {
            $query->lockForUpdate();
        }

        $latest = $query->value('sku');

        if ($latest && preg_match('/(\d+)$/', $latest, $matches)) {
            return ((int) $matches[1]) + 1;
        }

        return 1;
    }

    public function manufactureRecipes()
    {
        return $this->hasMany(ManufactureRecipe::class, 'parent_item_id');
    }

    public function manufactureJobs()
    {
        return $this->hasMany(ManufactureJob::class, 'parent_item_id');
    }
}
