<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FirehoseCouplingDetector
{
    private const KEYWORDS = ['coupling', 'kopling', 'instantaneous'];

    /**
     * @param  \Illuminate\Support\Collection<int, int>|array<int, int>  $parentItemIds
     * @return array<int, bool>
     */
    public function eligibleParentItemMap(Collection|array $parentItemIds): array
    {
        $ids = collect($parentItemIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $rows = DB::table('manufacture_recipes as recipe')
            ->leftJoin('items as component_item', 'component_item.id', '=', 'recipe.component_item_id')
            ->leftJoin('item_variants as component_variant', 'component_variant.id', '=', 'recipe.component_variant_id')
            ->leftJoin('items as variant_item', 'variant_item.id', '=', 'component_variant.item_id')
            ->whereIn('recipe.parent_item_id', $ids)
            ->get([
                'recipe.parent_item_id',
                'component_item.name as component_item_name',
                'component_item.sku as component_item_sku',
                'variant_item.name as variant_item_name',
                'variant_item.sku as variant_item_sku',
                'component_variant.sku as component_variant_sku',
            ]);

        $eligible = [];

        foreach ($rows as $row) {
            $haystacks = [
                (string) ($row->component_item_name ?? ''),
                (string) ($row->component_item_sku ?? ''),
                (string) ($row->variant_item_name ?? ''),
                (string) ($row->variant_item_sku ?? ''),
                (string) ($row->component_variant_sku ?? ''),
            ];

            foreach ($haystacks as $value) {
                $normalized = mb_strtolower($value, 'UTF-8');
                foreach (self::KEYWORDS as $keyword) {
                    if ($normalized !== '' && str_contains($normalized, $keyword)) {
                        $eligible[(int) $row->parent_item_id] = true;
                        continue 3;
                    }
                }
            }
        }

        return $eligible;
    }
}
