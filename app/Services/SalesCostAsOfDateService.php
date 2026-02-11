<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\PurchasePriceHistory;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class SalesCostAsOfDateService
{
    /** @var array<string, array{cost_unit:float|null, source:string, effective_date:string|null, cost_missing:bool}> */
    private array $resolvedCache = [];

    /** @var array<int, float|null> */
    private array $itemDefaultCache = [];

    /** @var array<int, float|null> */
    private array $variantDefaultCache = [];

    /**
     * @return array{cost_unit:float|null, source:string, effective_date:string|null, cost_missing:bool}
     */
    public function resolve(?int $itemId, ?int $variantId, CarbonInterface|string|null $soDate): array
    {
        $itemId = $itemId ?: null;
        if (!$itemId) {
            return [
                'cost_unit' => null,
                'source' => 'missing',
                'effective_date' => null,
                'cost_missing' => true,
            ];
        }

        $date = $soDate instanceof CarbonInterface
            ? $soDate->toDateString()
            : Carbon::parse($soDate ?: now())->toDateString();

        $cacheKey = implode('|', [$itemId, $variantId ?: 0, $date]);
        if (isset($this->resolvedCache[$cacheKey])) {
            return $this->resolvedCache[$cacheKey];
        }

        $variantId = $variantId ?: null;

        if ($variantId) {
            $variantHistory = PurchasePriceHistory::query()
                ->where('item_id', $itemId)
                ->where('item_variant_id', $variantId)
                ->whereDate('effective_date', '<=', $date)
                ->orderByDesc('effective_date')
                ->orderByDesc('id')
                ->first(['price', 'effective_date']);

            if ($variantHistory) {
                return $this->resolvedCache[$cacheKey] = [
                    'cost_unit' => (float) $variantHistory->price,
                    'source' => 'variant_history',
                    'effective_date' => optional($variantHistory->effective_date)->toDateString(),
                    'cost_missing' => false,
                ];
            }
        }

        $itemHistory = PurchasePriceHistory::query()
            ->where('item_id', $itemId)
            ->whereNull('item_variant_id')
            ->whereDate('effective_date', '<=', $date)
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->first(['price', 'effective_date']);

        if ($itemHistory) {
            return $this->resolvedCache[$cacheKey] = [
                'cost_unit' => (float) $itemHistory->price,
                'source' => 'item_history',
                'effective_date' => optional($itemHistory->effective_date)->toDateString(),
                'cost_missing' => false,
            ];
        }

        if ($variantId) {
            $variantDefault = $this->variantDefaultCost($variantId);
            if ($variantDefault !== null) {
                return $this->resolvedCache[$cacheKey] = [
                    'cost_unit' => $variantDefault,
                    'source' => 'variant_default',
                    'effective_date' => null,
                    'cost_missing' => false,
                ];
            }
        }

        $itemDefault = $this->itemDefaultCost($itemId);
        if ($itemDefault !== null) {
            return $this->resolvedCache[$cacheKey] = [
                'cost_unit' => $itemDefault,
                'source' => 'item_default',
                'effective_date' => null,
                'cost_missing' => false,
            ];
        }

        return $this->resolvedCache[$cacheKey] = [
            'cost_unit' => null,
            'source' => 'missing',
            'effective_date' => null,
            'cost_missing' => true,
        ];
    }

    private function itemDefaultCost(int $itemId): ?float
    {
        if (!array_key_exists($itemId, $this->itemDefaultCache)) {
            $this->itemDefaultCache[$itemId] = Item::query()
                ->whereKey($itemId)
                ->value('default_cost');
        }

        $value = $this->itemDefaultCache[$itemId];
        return $value !== null ? (float) $value : null;
    }

    private function variantDefaultCost(int $variantId): ?float
    {
        if (!array_key_exists($variantId, $this->variantDefaultCache)) {
            $this->variantDefaultCache[$variantId] = ItemVariant::query()
                ->whereKey($variantId)
                ->value('default_cost');
        }

        $value = $this->variantDefaultCache[$variantId];
        return $value !== null ? (float) $value : null;
    }
}
