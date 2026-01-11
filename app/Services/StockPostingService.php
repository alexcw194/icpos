<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class StockPostingService
{
    public static function postInbound(
        int $companyId,
        ?int $warehouseId,
        int $itemId,
        ?int $itemVariantId,
        float $qty,
        string $refType,
        int $refId,
        ?string $note = null,
        ?float $unitCost = null,
    ): void {
        DB::transaction(function () use ($companyId, $warehouseId, $itemId, $itemVariantId, $qty, $refType, $refId, $note, $unitCost) {

            // Lock stock row dulu untuk dapat qty_on_hand BEFORE dan aman dari race
            $stockRow = DB::table('item_stocks')
                ->where('company_id', $companyId)
                ->when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId))
                ->where('item_id', $itemId)
                ->when($itemVariantId, fn($q) => $q->where('item_variant_id', $itemVariantId))
                ->lockForUpdate();

            $stockExisting = $stockRow->first();
            $onHandBefore  = (float)($stockExisting->qty_on_hand ?? 0);

            // 1) Hitung balance terakhir (per dimensi yang sama)
            $last = DB::table('stock_movements')
                ->where('company_id', $companyId) // FIX bug dari compact('companyId')
                ->when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId))
                ->where('item_id', $itemId)
                ->when($itemVariantId, fn($q) => $q->where('item_variant_id', $itemVariantId))
                ->orderByDesc('id')
                ->first();

            $prevBalance = (float)($last->balance ?? 0);
            $newBalance  = $prevBalance + $qty;

            $valueChange = null;
            if ($unitCost !== null) {
                $valueChange = round($qty * $unitCost, 2);
            }

            // 2) Insert movement IN
            DB::table('stock_movements')->insert([
                'company_id'      => $companyId,
                'warehouse_id'    => $warehouseId,
                'item_id'         => $itemId,
                'item_variant_id' => $itemVariantId,
                'ref_type'        => $refType,   // 'GR'
                'ref_id'          => $refId,
                'qty_in'          => $qty,
                'qty_out'         => 0,
                'balance'         => $newBalance,
                'unit_cost'       => $unitCost,
                'value_change'    => $valueChange,
                'note'            => $note,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            // 3) Update qty_on_hand (ItemStock)
            if (!$stockExisting) {
                DB::table('item_stocks')->insert([
                    'company_id'      => $companyId,
                    'warehouse_id'    => $warehouseId,
                    'item_id'         => $itemId,
                    'item_variant_id' => $itemVariantId,
                    'qty_on_hand'     => $qty,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            } else {
                $stockRow->increment('qty_on_hand', $qty);
            }

            // 4) Rolling cost update (ItemVariant)
            if ($itemVariantId && $unitCost !== null) {
                // ambil avg_cost terakhir
                $variant = DB::table('item_variants')->where('id', $itemVariantId)->lockForUpdate()->first();

                $avgBefore = $variant?->avg_cost !== null ? (float)$variant->avg_cost : null;
                $baseCost  = $avgBefore
                    ?? ($variant?->last_cost !== null ? (float)$variant->last_cost : null)
                    ?? ($variant?->default_cost !== null ? (float)$variant->default_cost : null);

                // Weighted average: (onHandBefore*baseCost + qty*unitCost) / (onHandBefore+qty)
                $denom = $onHandBefore + $qty;

                $avgAfter = $unitCost; // fallback
                if ($denom > 0) {
                    $numer = (($onHandBefore > 0 && $baseCost !== null) ? ($onHandBefore * $baseCost) : 0)
                           + ($qty * $unitCost);
                    $avgAfter = $numer / $denom;
                }

                DB::table('item_variants')->where('id', $itemVariantId)->update([
                    'last_cost'   => round($unitCost, 2),
                    'avg_cost'    => round($avgAfter, 2),
                    'updated_at'  => now(),
                ]);
            }
        });
    }
}
