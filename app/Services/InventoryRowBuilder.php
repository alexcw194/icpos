<?php

namespace App\Services;

use App\Models\Item;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class InventoryRowBuilder
{
    public function buildFlatRows(Collection $items, array $filters): Collection
    {
        $rows = collect();
        $term = Str::lower($filters['q']);
        $sizeFilters  = array_map(fn($v) => Str::lower($v), $filters['sizes']);
        $colorFilters = array_map(fn($v) => Str::lower($v), $filters['colors']);
        $lengthMin = $filters['length_min'];
        $lengthMax = $filters['length_max'];

        $showVariantParent = $filters['show_variant_parent'];

        $variantMaxRelevance = [];
        $itemRelevance       = [];

        foreach ($items as $item) {
            $variants = $item->variants ?? collect();
            $displayVariants = $this->shouldDisplayVariants($item, $variants);
            $activeVariants = $displayVariants ? $variants->where('is_active', true) : collect();
            $totalVariantStock = (int) $activeVariants->sum('stock');
            $minVariantPrice   = $displayVariants ? $activeVariants->min('price') : null;
            $maxVariantPrice   = $displayVariants ? $activeVariants->max('price') : null;

            $itemPriceValue = $minVariantPrice ?? $item->price;
            $itemStockValue = $displayVariants ? $totalVariantStock : (int) $item->stock;

            $itemRelevanceScore = $this->computeInventoryRelevance($item, null, $term);
            $itemRelevance[$item->id] = $itemRelevanceScore;

            $itemMatchesAttributes = $this->itemMatchesAttributeFilters($item, $filters, $activeVariants);

            $shouldHideParent = !$showVariantParent && $displayVariants && $filters['type'] !== 'item';

            if ($filters['type'] !== 'variant' && $itemMatchesAttributes && !$shouldHideParent) {
                $rows->push([
                    'entity'        => 'item',
                    'item_id'       => $item->id,
                    'variant_id'    => null,
                    'display_name'  => $item->name,
                    'brand'         => optional($item->brand)->name,
                    'unit'          => optional($item->unit)->code ?: optional($item->unit)->name,
                    'sku'           => $item->sku,
                    'price'         => (float) ($itemPriceValue ?? 0),
                    'price_label'   => $this->formatPriceRange($minVariantPrice, $maxVariantPrice, $item->price),
                    'stock'         => $itemStockValue,
                    'stock_label'   => number_format($itemStockValue, 0, ',', '.'),
                    'low_stock'     => ($item->min_stock ?? 0) > 0 && $itemStockValue < $item->min_stock,
                    'inactive'      => false,
                    'attributes'    => [
                        'size'  => optional($item->size)->name,
                        'color' => optional($item->color)->name,
                    ],
                    'parent_name'   => null,
                    'relevance'     => $itemRelevanceScore,
                    'created_at'    => $item->created_at,
                    'variant_count' => $variants->count(),
                    'variants'      => $variants,
                ]);
            }

            if ($filters['type'] === 'item') {
                continue;
            }
            if (!$displayVariants) {
                continue;
            }

            foreach ($variants as $variant) {
                if (!$this->variantMatchesFilters($variant, $item, $filters, $sizeFilters, $colorFilters, $lengthMin, $lengthMax)) {
                    continue;
                }
                if ($filters['stock'] === 'gt0' && (int) $variant->stock <= 0) {
                    continue;
                }
                if ($filters['stock'] === 'eq0' && (int) $variant->stock > 0) {
                    continue;
                }

                $attrs = $variant->computed_attributes ?? ($variant->attributes ?? []);
                $label = $item->renderVariantLabel(is_array($attrs) ? $attrs : []);
                $relevance = $this->computeInventoryRelevance($item, $variant, $term);
                $variantMaxRelevance[$item->id] = max($variantMaxRelevance[$item->id] ?? 0, $relevance);

                $rows->push([
                    'entity'        => 'variant',
                    'item_id'       => $item->id,
                    'variant_id'    => $variant->id,
                    'display_name'  => $label,
                    'brand'         => optional($item->brand)->name,
                    'unit'          => optional($item->unit)->code ?: optional($item->unit)->name,
                    'sku'           => $variant->sku ?: $item->sku,
                    'price'         => (float) ($variant->price ?? $item->price ?? 0),
                    'price_label'   => 'Rp ' . number_format((float) ($variant->price ?? 0), 2, ',', '.'),
                    'stock'         => (int) $variant->stock,
                    'stock_label'   => number_format((int) $variant->stock, 0, ',', '.'),
                    'low_stock'     => ($variant->min_stock ?? 0) > 0 && $variant->stock < $variant->min_stock,
                    'inactive'      => !$variant->is_active,
                    'attributes'    => [
                        'size'  => $attrs['size'] ?? null,
                        'color' => $attrs['color'] ?? null,
                    ],
                    'parent_name'   => $item->name,
                    'relevance'     => $relevance,
                    'created_at'    => $variant->created_at,
                    'variant_count' => $variants->count(),
                    'variants'      => $variants,
                ]);
            }
        }

        if ($term !== '') {
            $rows = $rows->filter(function ($row) use ($term) {
                if ($row['relevance'] > 0) return true;
                if ($row['entity'] === 'item') {
                    return Str::contains(Str::lower($row['display_name']), $term);
                }
                return false;
            });

            $rows = $rows->filter(function ($row) use ($variantMaxRelevance, $itemRelevance) {
                if ($row['entity'] !== 'item') return true;
                $maxVariant = $variantMaxRelevance[$row['item_id']] ?? null;
                if ($maxVariant === null) return true;
                $itemScore = $itemRelevance[$row['item_id']] ?? 0;
                return $itemScore >= $maxVariant;
            });
        }

        return $this->sortInventoryRows($rows, $filters)->values();
    }

    public function buildGroupedRows(Collection $items, array $filters): Collection
    {
        $term = Str::lower($filters['q']);
        $sizeFilters  = array_map(fn($v) => Str::lower($v), $filters['sizes']);
        $colorFilters = array_map(fn($v) => Str::lower($v), $filters['colors']);
        $lengthMin = $filters['length_min'];
        $lengthMax = $filters['length_max'];
        $showVariantParent = $filters['show_variant_parent'];

        $rows = collect();

        foreach ($items as $item) {
            $variants = $item->variants ?? collect();
            $displayVariants = $this->shouldDisplayVariants($item, $variants);
            $activeVariants  = $displayVariants ? $variants->where('is_active', true) : collect();

            if ($filters['type'] === 'variant') {
                if (!$displayVariants) continue;
                $matchingVariants = $variants->filter(fn($variant) => $this->variantMatchesFilters($variant, $item, $filters, $sizeFilters, $colorFilters, $lengthMin, $lengthMax));
                if ($matchingVariants->isEmpty()) continue;
            } elseif (!$this->itemMatchesAttributeFilters($item, $filters, $activeVariants)) {
                continue;
            }

            $totalVariantStock = (int) $activeVariants->sum('stock');
            $minVariantPrice = $displayVariants ? $activeVariants->min('price') : null;
            $maxVariantPrice = $displayVariants ? $activeVariants->max('price') : null;
            $priceLabel = $this->formatPriceRange($minVariantPrice, $maxVariantPrice, $item->price);
            $stockLabel = $activeVariants->isNotEmpty()
                ? number_format($totalVariantStock, 0, ',', '.')
                : number_format((int) $item->stock, 0, ',', '.');

            $chipData = $this->buildAttributeChipData($activeVariants);

            $preview = $activeVariants->sortBy('id')->take(5)->map(function ($variant) use ($item) {
                $attrs = $variant->attributes ?? [];
                return [
                    'label'  => $item->renderVariantLabel(is_array($attrs) ? $attrs : []),
                    'sku'    => $variant->sku ?: $item->sku,
                    'price'  => number_format((float) ($variant->price ?? 0), 2, ',', '.'),
                    'stock'  => (int) $variant->stock,
                    'active' => (bool) $variant->is_active,
                ];
            });

            if (!$showVariantParent && $displayVariants && $filters['type'] !== 'item') {
                continue;
            }

            $rows->push([
                'item'          => $item,
                'variant_count' => $variants->count(),
                'price_label'   => $priceLabel,
                'stock_label'   => $stockLabel,
                'chips'         => $chipData,
                'preview'       => $preview,
                'has_variants'  => $displayVariants,
                'relevance'     => $this->computeInventoryRelevance($item, null, $term),
            ]);
        }

        return $rows->sortBy('item.name')->values();
    }

    private function sortInventoryRows(Collection $rows, array $filters): Collection
    {
        return $rows->sort(function ($a, $b) use ($filters) {
            if ($filters['q'] !== '') {
                if (($b['relevance'] ?? 0) !== ($a['relevance'] ?? 0)) {
                    return ($b['relevance'] ?? 0) <=> ($a['relevance'] ?? 0);
                }
            }

            switch ($filters['sort']) {
                case 'price_lowest':
                    return ($a['price'] ?? 0) <=> ($b['price'] ?? 0);
                case 'price_highest':
                    return ($b['price'] ?? 0) <=> ($a['price'] ?? 0);
                case 'stock_highest':
                    return ($b['stock'] ?? 0) <=> ($a['stock'] ?? 0);
                case 'newest':
                    return ($b['created_at'] ?? now()) <=> ($a['created_at'] ?? now());
                case 'name_asc':
                default:
                    return Str::lower($a['display_name'] ?? '') <=> Str::lower($b['display_name'] ?? '');
            }
        });
    }

    private function computeInventoryRelevance(Item $item, $variant, string $term): int
    {
        if ($term === '') return 0;

        $score = 0;
        $term = Str::lower($term);

        if (Str::contains(Str::lower($item->name), $term)) {
            $score += 200;
        }
        if ($item->sku && Str::contains(Str::lower($item->sku), $term)) {
            $score += 150;
        }
        if ($item->brand && Str::contains(Str::lower($item->brand->name), $term)) {
            $score += 80;
        }

        if ($variant) {
            if ($variant->sku && Str::contains(Str::lower($variant->sku), $term)) {
                $score += 1000;
            }
            $attrs = $variant->attributes ?? [];
            foreach ($attrs as $value) {
                if (is_string($value) && Str::contains(Str::lower($value), $term)) {
                    $score += 500;
                }
            }
        }

        return $score;
    }

    private function variantMatchesFilters($variant, Item $item, array $filters, array $sizeFilters, array $colorFilters, ?float $lengthMin, ?float $lengthMax): bool
    {
        $attrs = $variant->attributes ?? [];
        $size  = Str::lower($attrs['size'] ?? '');
        $color = Str::lower($attrs['color'] ?? '');
        $lengthValue = $this->normalizeLengthValue($attrs['length'] ?? null);

        if ($sizeFilters && !in_array($size, $sizeFilters, true))  return false;
        if ($colorFilters && !in_array($color, $colorFilters, true)) return false;
        if ($lengthMin !== null && ($lengthValue === null || $lengthValue < $lengthMin)) return false;
        if ($lengthMax !== null && ($lengthValue === null || $lengthValue > $lengthMax)) return false;

        return true;
    }

    private function itemMatchesAttributeFilters(Item $item, array $filters, Collection $activeVariants): bool
    {
        if (empty($filters['sizes']) && empty($filters['colors']) && $filters['length_min'] === null && $filters['length_max'] === null) {
            return true;
        }

        $sizeFilters  = array_map(fn($v) => Str::lower($v), $filters['sizes']);
        $colorFilters = array_map(fn($v) => Str::lower($v), $filters['colors']);

        foreach ($activeVariants as $variant) {
            if ($this->variantMatchesFilters($variant, $item, $filters, $sizeFilters, $colorFilters, $filters['length_min'], $filters['length_max'])) {
                return true;
            }
        }

        return false;
    }

    private function shouldDisplayVariants(Item $item, Collection $variants): bool
    {
        if ($variants->isEmpty()) return false;

        if (($item->variant_type ?? 'none') !== 'none') return true;
        if ($variants->count() > 1) return true;

        $variant = $variants->first();
        if (!$variant) return false;

        $attrs = $variant->attributes ?? [];
        $hasAttributes = collect($attrs)
            ->filter(fn($v) => $v !== null && $v !== '')
            ->isNotEmpty();

        if ($hasAttributes) return true;

        $hasDifferentSku   = $variant->sku && $variant->sku !== $item->sku;
        $hasDifferentPrice = $variant->price !== null && (float) $variant->price !== (float) $item->price;

        return $hasDifferentSku || $hasDifferentPrice;
    }

    private function normalizeLengthValue($value): ?float
    {
        if ($value === null || $value === '') return null;
        if (is_numeric($value)) return (float) $value;

        $clean = preg_replace('/[^0-9.,-]/', '', (string) $value);
        $clean = str_replace(',', '.', $clean);
        return is_numeric($clean) ? (float) $clean : null;
    }

    private function formatPriceRange($minVariantPrice, $maxVariantPrice, $itemPrice): string
    {
        if ($minVariantPrice !== null && $maxVariantPrice !== null) {
            if ($minVariantPrice == $maxVariantPrice) {
                return 'Rp ' . number_format((float) $minVariantPrice, 2, ',', '.');
            }
            return 'Rp ' . number_format((float) $minVariantPrice, 2, ',', '.')
                 . ' - ' . number_format((float) $maxVariantPrice, 2, ',', '.');
        }
        return 'Rp ' . number_format((float) ($itemPrice ?? 0), 2, ',', '.');
    }

    private function buildAttributeChipData(Collection $variants): array
    {
        $sizes  = [];
        $colors = [];

        foreach ($variants as $variant) {
            $attrs = $variant->attributes ?? [];
            if (!empty($attrs['size']))  $sizes[]  = (string) $attrs['size'];
            if (!empty($attrs['color'])) $colors[] = (string) $attrs['color'];
        }

        $sizes  = collect($sizes)->unique()->values();
        $colors = collect($colors)->unique()->values();

        return [
            'size'  => $sizes,
            'color' => $colors,
        ];
    }
}
