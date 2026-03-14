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
        $term = trim((string) ($filters['q'] ?? ''));
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
            $activeVariants = $displayVariants
                ? $variants->filter(fn ($variant) => $this->isVariantActive($variant))
                : collect();
            $totalVariantStock = (float) $activeVariants->sum(fn ($variant) => $this->resolveVariantStock($variant));
            $minVariantPrice   = $displayVariants ? $activeVariants->min('price') : null;
            $maxVariantPrice   = $displayVariants ? $activeVariants->max('price') : null;

            $itemPriceValue = $minVariantPrice ?? $item->price;
            $itemStockValue = $displayVariants ? $totalVariantStock : $this->resolveItemBaseStock($item);

            $itemRelevanceScore = $this->computeInventoryRelevance($item, null, $term);
            $itemRelevance[$item->id] = $itemRelevanceScore;

            $itemMatchesAttributes = $this->itemMatchesAttributeFilters($item, $filters, $activeVariants);
            $passesStockFilter = $this->passesStockFilter($itemStockValue, $filters['stock'] ?? 'all');

            $shouldHideParent = !$showVariantParent && $displayVariants && $filters['type'] !== 'item';

            if ($filters['type'] !== 'variant' && $itemMatchesAttributes && $passesStockFilter && !$shouldHideParent) {
                $rows->push([
                    'entity'        => 'item',
                    'item_id'       => $item->id,
                    'variant_id'    => null,
                    'display_name'  => $item->name,
                    'brand'         => optional($item->brand)->name,
                    'family_code'   => $item->family_code,
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
                $variantStock = $this->resolveVariantStock($variant);
                if (!$this->passesStockFilter($variantStock, $filters['stock'] ?? 'all')) {
                    continue;
                }

                $attrs = $variant->computed_attributes ?? ($variant->attributes ?? []);
                $label = $item->renderVariantDisplayName(is_array($attrs) ? $attrs : [], $variant->sku);
                $relevance = $this->computeInventoryRelevance($item, $variant, $term);
                $variantMaxRelevance[$item->id] = max($variantMaxRelevance[$item->id] ?? 0, $relevance);

                $rows->push([
                    'entity'        => 'variant',
                    'item_id'       => $item->id,
                    'variant_id'    => $variant->id,
                    'display_name'  => $label,
                    'brand'         => optional($item->brand)->name,
                    'family_code'   => $item->family_code,
                    'unit'          => optional($item->unit)->code ?: optional($item->unit)->name,
                    'sku'           => $variant->sku ?: $item->sku,
                    'price'         => (float) ($variant->price ?? $item->price ?? 0),
                    'price_label'   => 'Rp ' . number_format((float) ($variant->price ?? 0), 2, ',', '.'),
                    'stock'         => $variantStock,
                    'stock_label'   => number_format($variantStock, 0, ',', '.'),
                    'low_stock'     => ($variant->min_stock ?? 0) > 0 && $variantStock < $variant->min_stock,
                    'inactive'      => !$this->isVariantActive($variant),
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
                    return $this->matchesInventorySearchTerm((string) ($row['display_name'] ?? ''), $term);
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
            $activeVariants  = $displayVariants
                ? $variants->filter(fn ($variant) => $this->isVariantActive($variant))
                : collect();

            if ($filters['type'] === 'variant') {
                if (!$displayVariants) continue;
                $matchingVariants = $variants->filter(function ($variant) use ($item, $filters, $sizeFilters, $colorFilters, $lengthMin, $lengthMax) {
                    if (!$this->variantMatchesFilters($variant, $item, $filters, $sizeFilters, $colorFilters, $lengthMin, $lengthMax)) {
                        return false;
                    }
                    return $this->passesStockFilter($this->resolveVariantStock($variant), $filters['stock'] ?? 'all');
                });
                if ($matchingVariants->isEmpty()) continue;
            } elseif (!$this->itemMatchesAttributeFilters($item, $filters, $activeVariants)) {
                continue;
            }

            $totalVariantStock = (float) $activeVariants->sum(fn ($variant) => $this->resolveVariantStock($variant));
            $minVariantPrice = $displayVariants ? $activeVariants->min('price') : null;
            $maxVariantPrice = $displayVariants ? $activeVariants->max('price') : null;
            $priceLabel = $this->formatPriceRange($minVariantPrice, $maxVariantPrice, $item->price);
            $itemBaseStock = $this->resolveItemBaseStock($item);
            $resolvedGroupStock = $activeVariants->isNotEmpty() ? $totalVariantStock : $itemBaseStock;
            if (!$this->passesStockFilter($resolvedGroupStock, $filters['stock'] ?? 'all')) {
                continue;
            }
            $stockLabel = $activeVariants->isNotEmpty()
                ? number_format($totalVariantStock, 0, ',', '.')
                : number_format($itemBaseStock, 0, ',', '.');

            $chipData = $this->buildAttributeChipData($activeVariants);

            $preview = $activeVariants->sortBy('id')->take(5)->map(function ($variant) use ($item) {
                $attrs = $variant->attributes ?? [];
                $variantStock = $this->resolveVariantStock($variant);
                return [
                    'label'  => $item->renderVariantDisplayName(is_array($attrs) ? $attrs : [], $variant->sku),
                    'sku'    => $variant->sku ?: $item->sku,
                    'price'  => number_format((float) ($variant->price ?? 0), 2, ',', '.'),
                    'stock'  => number_format($variantStock, 0, ',', '.'),
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
        $term = trim($term);
        if ($term === '') return 0;

        $score = 0;
        $tokens = collect(preg_split('/[\s\-\/]+/u', mb_strtolower($term, 'UTF-8')))
            ->map(fn ($token) => trim((string) $token))
            ->filter(fn ($token) => $token !== '')
            ->unique()
            ->values();

        foreach ($this->inventorySearchCandidates($item, $variant) as $candidate => $candidateWeight) {
            if ($this->matchesInventorySearchTerm($candidate, $term)) {
                $score += $candidateWeight;
            }

            if ($tokens->count() > 1) {
                $tokenMatches = 0;
                foreach ($tokens as $token) {
                    if ($this->matchesInventorySearchTerm($candidate, $token)) {
                        $tokenMatches++;
                    }
                }

                if ($tokenMatches === $tokens->count()) {
                    $score += (int) round($candidateWeight * 0.75);
                } elseif ($tokenMatches > 0) {
                    $score += (int) round(($candidateWeight * 0.4) * ($tokenMatches / $tokens->count()));
                }
            }
        }

        return $score;
    }

    private function inventorySearchCandidates(Item $item, $variant): array
    {
        $candidates = [];

        if ((string) $item->name !== '') {
            $candidates[(string) $item->name] = 200;
        }
        if ((string) ($item->sku ?? '') !== '') {
            $candidates[(string) $item->sku] = 150;
        }
        if ($item->brand && (string) $item->brand->name !== '') {
            $candidates[(string) $item->brand->name] = 80;
        }

        if ($variant) {
            if ((string) ($variant->sku ?? '') !== '') {
                $candidates[(string) $variant->sku] = 1000;
            }
            $attrs = $variant->computed_attributes ?? ($variant->attributes ?? []);
            $displayName = $item->renderVariantDisplayName(is_array($attrs) ? $attrs : [], $variant->sku);
            if ($displayName !== '') {
                $candidates[$displayName] = 1200;
            }
            foreach ($attrs as $value) {
                if (is_scalar($value) && trim((string) $value) !== '') {
                    $candidates[(string) $value] = 500;
                }
            }
        }

        return $candidates;
    }

    private function matchesInventorySearchTerm(string $value, string $term): bool
    {
        $value = trim($value);
        $term = trim($term);

        if ($value === '' || $term === '') {
            return false;
        }

        $valueLower = mb_strtolower($value, 'UTF-8');
        $termLower = mb_strtolower($term, 'UTF-8');
        if (Str::contains($valueLower, $termLower)) {
            return true;
        }

        return $this->normalizeInventorySearchText($valueLower) !== ''
            && Str::contains($this->normalizeInventorySearchText($valueLower), $this->normalizeInventorySearchText($termLower));
    }

    private function normalizeInventorySearchText(string $value): string
    {
        return str_replace([' ', '-', '/'], '', $value);
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

        $activeVariants = $variants->filter(fn ($variant) => $this->isVariantActive($variant));
        if ($activeVariants->isEmpty()) {
            return false;
        }

        if (($item->variant_type ?? 'none') !== 'none') return true;
        if ($activeVariants->count() > 1) return true;

        $variant = $activeVariants->first();
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

    private function isVariantActive($variant): bool
    {
        if ($variant === null) {
            return false;
        }

        $flag = $variant->is_active ?? null;
        return $flag === null || (bool) $flag;
    }

    private function resolveItemBaseStock(Item $item): float
    {
        if (isset($item->computed_stock_base) && is_numeric($item->computed_stock_base)) {
            return (float) $item->computed_stock_base;
        }

        return (float) ($item->stock ?? 0);
    }

    private function resolveVariantStock($variant): float
    {
        if ($variant !== null && isset($variant->computed_stock) && is_numeric($variant->computed_stock)) {
            return (float) $variant->computed_stock;
        }

        return (float) ($variant->stock ?? 0);
    }

    private function passesStockFilter(float $stock, string $stockFilter): bool
    {
        if ($stockFilter === 'gt0') {
            return $stock > 0;
        }
        if ($stockFilter === 'eq0') {
            return $stock <= 0;
        }

        return true;
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
