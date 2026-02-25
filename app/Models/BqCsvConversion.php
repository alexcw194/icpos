<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class BqCsvConversion extends Model
{
    protected $fillable = [
        'source_category',
        'source_item',
        'source_category_norm',
        'source_item_norm',
        'mapped_item',
        'target_source_type',
        'target_item_id',
        'target_item_variant_id',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'target_item_id' => 'integer',
        'target_item_variant_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (BqCsvConversion $row) {
            $row->source_category = trim((string) $row->source_category);
            $row->source_item = trim((string) $row->source_item);
            $row->mapped_item = trim((string) $row->mapped_item);
            $row->source_category_norm = self::normalizeTerm($row->source_category);
            $row->source_item_norm = self::normalizeTerm($row->source_item);
            $sourceType = trim((string) ($row->target_source_type ?? ''));
            $row->target_source_type = in_array($sourceType, ['item', 'project'], true)
                ? $sourceType
                : null;
        });
    }

    public static function normalizeTerm(?string $value): string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/\s+/', ' ', $value) ?? '';

        return mb_strtolower(trim($value));
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function targetItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'target_item_id');
    }

    public function targetItemVariant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class, 'target_item_variant_id');
    }

    public static function sourceTypeFromItemListType(string $listType): string
    {
        return $listType === 'project' ? 'project' : 'item';
    }

    public static function validateVariantBelongsToItem(?int $itemId, ?int $variantItemId): void
    {
        if ($itemId && $variantItemId && $itemId !== $variantItemId) {
            throw new InvalidArgumentException('Variant tidak sesuai dengan item.');
        }
    }
}
