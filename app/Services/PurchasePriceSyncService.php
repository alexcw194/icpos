<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchasePriceHistory;
use Carbon\Carbon;

class PurchasePriceSyncService
{
    /**
     * @return array{
     *   processed_lines:int,
     *   updated_items:int,
     *   updated_variants:int,
     *   history_created:int,
     *   history_updated:int,
     *   skipped:int
     * }
     */
    public function syncFromApprovedPurchaseOrder(PurchaseOrder $purchaseOrder, bool $dryRun = false): array
    {
        $purchaseOrder->loadMissing('lines');

        $approvedAt = $purchaseOrder->approved_at
            ? Carbon::parse($purchaseOrder->approved_at)
            : now();
        $effectiveDate = $approvedAt->toDateString();

        $stats = [
            'processed_lines' => 0,
            'updated_items' => 0,
            'updated_variants' => 0,
            'history_created' => 0,
            'history_updated' => 0,
            'skipped' => 0,
        ];

        foreach ($purchaseOrder->lines as $line) {
            $stats['processed_lines']++;

            if (!$line->item_id) {
                $stats['skipped']++;
                continue;
            }

            $price = (float) ($line->unit_price ?? 0);
            $historyExists = false;

            if ($line->id) {
                $historyExists = PurchasePriceHistory::query()
                    ->where('purchase_order_line_id', $line->id)
                    ->exists();
            }

            if ($line->item_variant_id) {
                if (!$dryRun) {
                    ItemVariant::query()
                        ->whereKey($line->item_variant_id)
                        ->update([
                            'last_cost' => $price,
                            'last_cost_at' => $approvedAt,
                            'updated_at' => now(),
                        ]);
                }
                $stats['updated_variants']++;
            } else {
                if (!$dryRun) {
                    Item::query()
                        ->whereKey($line->item_id)
                        ->update([
                            'last_cost' => $price,
                            'last_cost_at' => $approvedAt,
                            'updated_at' => now(),
                        ]);
                }
                $stats['updated_items']++;
            }

            if ($dryRun) {
                if ($historyExists) {
                    $stats['history_updated']++;
                } else {
                    $stats['history_created']++;
                }
                continue;
            }

            $history = PurchasePriceHistory::query()->updateOrCreate(
                ['purchase_order_line_id' => $line->id],
                [
                    'item_id' => $line->item_id,
                    'item_variant_id' => $line->item_variant_id,
                    'price' => $price,
                    'effective_date' => $effectiveDate,
                    'purchase_order_id' => $purchaseOrder->id,
                    'source_company_id' => $purchaseOrder->company_id,
                ]
            );

            if ($history->wasRecentlyCreated) {
                $stats['history_created']++;
            } else {
                $stats['history_updated']++;
            }
        }

        return $stats;
    }
}
