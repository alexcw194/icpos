<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class StockPostingService
{
    /**
     * Post INBOUND stock (GR) — simetris dengan OUTBOUND (Delivery).
     * Assumsi tabel: stock_movements (qty_in, qty_out, balance), item_stocks (qty_on_hand).
     */
    public static function postInbound(
        int $companyId,
        ?int $warehouseId,
        int $itemId,
        ?int $itemVariantId,
        float $qty,
        string $refType,
        int $refId,
        ?string $note = null,
    ): void {
        DB::transaction(function () use ($companyId, $warehouseId, $itemId, $itemVariantId, $qty, $refType, $refId, $note) {

            // 1) Hitung balance terakhir (per dimensi yang sama)
            $last = DB::table('stock_movements')
                ->where(compact('companyId'))
                ->when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId))
                ->where('item_id', $itemId)
                ->when($itemVariantId, fn($q) => $q->where('item_variant_id', $itemVariantId))
                ->orderByDesc('id')->first();

            $prevBalance = (float)($last->balance ?? 0);
            $newBalance  = $prevBalance + $qty;

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
                'note'            => $note,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            // 3) Update qty_on_hand (ItemStock)
            $stockRow = DB::table('item_stocks')->where([
                'company_id'      => $companyId,
                'warehouse_id'    => $warehouseId,
                'item_id'         => $itemId,
                'item_variant_id' => $itemVariantId,
            ]);

            if ($stockRow->doesntExist()) {
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
        });
    }
}
