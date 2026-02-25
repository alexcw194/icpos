<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BqCsvConversion extends Model
{
    protected $fillable = [
        'source_category',
        'source_item',
        'source_category_norm',
        'source_item_norm',
        'mapped_item',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (BqCsvConversion $row) {
            $row->source_category = trim((string) $row->source_category);
            $row->source_item = trim((string) $row->source_item);
            $row->mapped_item = trim((string) $row->mapped_item);
            $row->source_category_norm = self::normalizeTerm($row->source_category);
            $row->source_item_norm = self::normalizeTerm($row->source_item);
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
}
