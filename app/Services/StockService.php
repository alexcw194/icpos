<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\DeliveryLine;
use App\Models\ItemStock;
use App\Models\StockAdjustment;
use App\Models\StockSummary;
use App\Models\StockLedger;
use App\Models\Warehouse;
use App\Models\SalesOrder;
use App\Services\DocNumberService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StockService
{

    private static function adjustSalesOrderDelivered(DeliveryLine $line, float $delta): void
    {
        if (!$line->sales_order_line_id || $delta === 0.0) return;

        // Lock the SO line, bump delivered qty (bounded ≥ 0)
        $soLine = DB::table('sales_order_lines')
            ->where('id', $line->sales_order_line_id)
            ->lockForUpdate()
            ->first();
        if (!$soLine) return;

        $newDelivered = max(0, (float)($soLine->qty_delivered ?? 0) + $delta);

        DB::table('sales_order_lines')
            ->where('id', $soLine->id)
            ->update(['qty_delivered' => $newDelivered]);

        // Optional: mirror to quotation_lines if present
        if ($line->quotation_line_id ?? null) {
            DB::table('quotation_lines')
                ->where('id', $line->quotation_line_id)
                ->update(['qty_delivered' => $newDelivered]); // or clamp to that doc’s logic
        }

        // NOTE: Delivery progress should not change SO billing status.
    }

    public static function postDelivery(Delivery $delivery, ?int $actingUserId = null): void
    {
        if ($delivery->status === Delivery::STATUS_POSTED) {
            return;
        }

        DB::transaction(function () use ($delivery, $actingUserId) {
            $lockedDelivery = Delivery::query()
                ->whereKey($delivery->getKey())
                ->lockForUpdate()
                ->with(['lines', 'warehouse', 'company'])
                ->firstOrFail();

            $warehouse = $lockedDelivery->warehouse;
            if (!$warehouse) {
                throw new RuntimeException('Warehouse is required before posting delivery.');
            }

            $company = $lockedDelivery->company;
            if (!$company) {
                throw new RuntimeException('Company is required before posting delivery.');
            }

            static::ensureSufficientStock($warehouse, $lockedDelivery->lines);

            $timestamp = Carbon::now();
            $movementDate = $lockedDelivery->date ? $lockedDelivery->date->copy() : $timestamp;
            $userId = static::resolveUserId($actingUserId);

            if (!$lockedDelivery->number) {
                $lockedDelivery->number = DocNumberService::next('delivery', $company, $movementDate);
            }

            foreach ($lockedDelivery->lines as $line) {
                static::deductStock(
                    companyId: $lockedDelivery->company_id,
                    warehouseId: $warehouse->id,
                    itemId: $line->item_id,
                    variantId: $line->item_variant_id,
                    qty: (float) $line->qty,
                    allowNegative: (bool) $warehouse->allow_negative_stock,
                    referenceType: 'delivery',
                    referenceId: $lockedDelivery->id,
                    userId: $userId,
                    date: $movementDate
                );

                static::adjustDeliveredQuantity($line, (float) $line->qty);
                static::adjustSalesOrderDelivered($line, (float) $line->qty);
            }

            $lockedDelivery->forceFill([
                'number'     => $lockedDelivery->number,
                'status'     => Delivery::STATUS_POSTED,
                'posted_at'  => $timestamp,
                'posted_by'  => $userId,
                'cancelled_at' => null,
                'cancelled_by' => null,
                'cancel_reason' => null,
            ])->save();

            if ($lockedDelivery->sales_order_id) {
                app(\App\Services\SalesOrderStatusSyncService::class)
                    ->syncById((int) $lockedDelivery->sales_order_id);
            }
        });

        $delivery->refresh();
    }

    public static function cancelDelivery(Delivery $delivery, ?int $actingUserId = null, ?string $reason = null): void
    {
        if ($delivery->status === Delivery::STATUS_CANCELLED) {
            return;
        }

        DB::transaction(function () use ($delivery, $actingUserId, $reason) {
            $lockedDelivery = Delivery::query()
                ->whereKey($delivery->getKey())
                ->lockForUpdate()
                ->with(['lines', 'warehouse'])
                ->firstOrFail();

            $timestamp = Carbon::now();
            $movementDate = $lockedDelivery->date ? $lockedDelivery->date->copy() : $timestamp;
            $userId = static::resolveUserId($actingUserId);

            if ($lockedDelivery->status === Delivery::STATUS_POSTED) {
                $warehouse = $lockedDelivery->warehouse;
                if (!$warehouse) {
                    throw new RuntimeException('Warehouse is required before cancelling delivery.');
                }

                foreach ($lockedDelivery->lines as $line) {
                    static::addStock(
                        companyId: $lockedDelivery->company_id,
                        warehouseId: $warehouse->id,
                        itemId: $line->item_id,
                        variantId: $line->item_variant_id,
                        qty: (float) $line->qty,
                        referenceType: 'delivery_cancel',
                        referenceId: $lockedDelivery->id,
                        userId: $userId,
                        date: $movementDate
                    );

                    static::adjustDeliveredQuantity($line, -1 * (float) $line->qty);
                    static::adjustSalesOrderDelivered($line, -1 * (float) $line->qty);
                }
            }

            $lockedDelivery->forceFill([
                'status'        => Delivery::STATUS_CANCELLED,
                'cancelled_at'  => $timestamp,
                'cancelled_by'  => $userId,
                'cancel_reason' => $reason,
            ])->save();

            if ($lockedDelivery->sales_order_id) {
                app(\App\Services\SalesOrderStatusSyncService::class)
                    ->syncById((int) $lockedDelivery->sales_order_id);
            }
        });

        $delivery->refresh();
    }

    public function manualAdjust(
        int $companyId,
        int $warehouseId,
        int $itemId,
        ?int $variantId,
        float $qtyAdjustment,
        ?string $reason = null,
        ?int $referenceId = null,
        ?Carbon $ledgerDate = null,
        ?int $actingUserId = null
    ): void {
        DB::transaction(function () use (
            $companyId,
            $warehouseId,
            $itemId,
            $variantId,
            $qtyAdjustment,
            $referenceId,
            $ledgerDate,
            $actingUserId
        ) {
            $stock = static::getStockForUpdate($companyId, $warehouseId, $itemId, $variantId);

            $balance = (float) $stock->qty_on_hand + (float) $qtyAdjustment;
            if ($balance < -1e-9) {
                throw new RuntimeException('Stok tidak boleh minus.');
            }

            $stock->qty_on_hand = $balance;
            $stock->save();

            $userId = static::resolveUserId($actingUserId);
            $movementDate = $ledgerDate ? $ledgerDate->copy() : Carbon::now();

            StockLedger::create([
                'company_id'      => $companyId,
                'warehouse_id'    => $warehouseId,
                'item_id'         => $itemId,
                'item_variant_id' => $variantId,
                'ledger_date'     => $movementDate,
                'qty_change'      => (float) $qtyAdjustment,
                'balance_after'   => $balance,
                'reference_type'  => 'manual_adjustment',
                'reference_id'    => $referenceId,
                'created_by'      => $userId,
            ]);

            static::syncSummary(
                companyId: $companyId,
                warehouseId: $warehouseId,
                itemId: $itemId,
                variantId: $variantId,
                balance: $balance,
                uom: $stock->item->unit->code ?? null
            );
        });
    }

    public function deleteManualAdjustment(StockAdjustment $adjustment, ?int $actingUserId = null): void
    {
        DB::transaction(function () use ($adjustment, $actingUserId) {
            $lockedAdjustment = StockAdjustment::query()
                ->whereKey($adjustment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (empty($lockedAdjustment->warehouse_id)) {
                throw new RuntimeException('Warehouse is required to delete stock adjustment.');
            }

            $companyId = (int) $lockedAdjustment->company_id;
            $warehouseId = (int) $lockedAdjustment->warehouse_id;
            $itemId = (int) $lockedAdjustment->item_id;
            $variantId = $lockedAdjustment->variant_id ? (int) $lockedAdjustment->variant_id : null;
            $qtyAdjustment = (float) $lockedAdjustment->qty_adjustment;

            $stock = static::getStockForUpdate($companyId, $warehouseId, $itemId, $variantId);
            $newBalance = (float) $stock->qty_on_hand - $qtyAdjustment;
            $stock->qty_on_hand = $newBalance;
            $stock->save();

            StockLedger::query()
                ->where('reference_type', 'manual_adjustment')
                ->where('reference_id', (int) $lockedAdjustment->id)
                ->delete();

            static::syncSummary(
                companyId: $companyId,
                warehouseId: $warehouseId,
                itemId: $itemId,
                variantId: $variantId,
                balance: $newBalance,
                uom: $stock->item->unit->code ?? null
            );

            static::recomputeLedgerBalancesForScope(
                companyId: $companyId,
                warehouseId: $warehouseId,
                itemId: $itemId,
                variantId: $variantId,
                currentBalance: $newBalance
            );

            $lockedAdjustment->delete();
        });
    }

    public static function ensureSufficientStock(Warehouse $warehouse, EloquentCollection $lines): void
    {
        $lines->each(function (DeliveryLine $line) use ($warehouse) {
            if (!$line->item_id) {
                return;
            }

            $stockQuery = ItemStock::query()
                ->where('company_id', $warehouse->company_id)
                ->where('warehouse_id', $warehouse->id)
                ->where('item_id', $line->item_id);
            static::applyVariantScope($stockQuery, $line->item_variant_id, 'item_variant_id');

            $available = (float) $stockQuery->sum('qty_on_hand');
            if (!$warehouse->allow_negative_stock && $available < (float) $line->qty) {
                throw new RuntimeException(
                    sprintf(
                        'Stok tidak cukup untuk item %s. Tersedia: %s, diminta: %s',
                        $line->description ?? ('#' . $line->item_id),
                        $available,
                        (float) $line->qty
                    )
                );
            }
        });
    }

    private static function deductStock(
        int $companyId,
        int $warehouseId,
        ?int $itemId,
        ?int $variantId,
        float $qty,
        bool $allowNegative,
        string $referenceType,
        ?int $referenceId,
        ?int $userId,
        Carbon $date
    ): void {
        if (!$itemId) {
            return;
        }

        $stock = static::getStockForUpdate($companyId, $warehouseId, $itemId, $variantId);

        $balance = (float) $stock->qty_on_hand - $qty;
        if (!$allowNegative && $balance < 0) {
            throw new RuntimeException('Stok tidak cukup.');
        }

        $stock->qty_on_hand = $balance;
        $stock->save();

        StockLedger::create([
            'company_id'      => $companyId,
            'warehouse_id'    => $warehouseId,
            'item_id'         => $itemId,
            'item_variant_id' => $variantId,
            'ledger_date'     => $date,
            'qty_change'      => -abs($qty),
            'balance_after'   => $balance,
            'reference_type'  => $referenceType,
            'reference_id'    => $referenceId,
            'created_by'      => $userId,
        ]);

        static::syncSummary(
            companyId: $companyId,
            warehouseId: $warehouseId,
            itemId: $itemId,
            variantId: $variantId,
            balance: $balance,
            uom: $stock->item->unit->code ?? null
        );
    }

    private static function addStock(
        int $companyId,
        int $warehouseId,
        ?int $itemId,
        ?int $variantId,
        float $qty,
        string $referenceType,
        ?int $referenceId,
        ?int $userId,
        Carbon $date
    ): void {
        if (!$itemId) {
            return;
        }

        $stock = static::getStockForUpdate($companyId, $warehouseId, $itemId, $variantId);

        $balance = (float) $stock->qty_on_hand + $qty;
        $stock->qty_on_hand = $balance;
        $stock->save();

        StockLedger::create([
            'company_id'      => $companyId,
            'warehouse_id'    => $warehouseId,
            'item_id'         => $itemId,
            'item_variant_id' => $variantId,
            'ledger_date'     => $date,
            'qty_change'      => abs($qty),
            'balance_after'   => $balance,
            'reference_type'  => $referenceType,
            'reference_id'    => $referenceId,
            'created_by'      => $userId,
        ]);

        static::syncSummary(
            companyId: $companyId,
            warehouseId: $warehouseId,
            itemId: $itemId,
            variantId: $variantId,
            balance: $balance,
            uom: $stock->item->unit->code ?? null
        );
    }

    private static function getStockForUpdate(int $companyId, int $warehouseId, int $itemId, ?int $variantId): ItemStock
    {
        $query = ItemStock::query()
            ->where('company_id', $companyId)
            ->where('warehouse_id', $warehouseId)
            ->where('item_id', $itemId);
        static::applyVariantScope($query, $variantId, 'item_variant_id');

        $rows = $query
            ->lockForUpdate()
            ->orderBy('id')
            ->get();

        $stock = $rows->first();

        if (!$stock) {
            $stock = new ItemStock([
                'company_id'      => $companyId,
                'warehouse_id'    => $warehouseId,
                'item_id'         => $itemId,
                'item_variant_id' => $variantId,
            ]);
            $stock->qty_on_hand = 0;
            return $stock;
        }

        if ($rows->count() > 1) {
            $mergedQty = (float) $rows->sum(fn (ItemStock $row) => (float) $row->qty_on_hand);

            $stock->qty_on_hand = $mergedQty;
            $stock->save();

            $duplicateIds = $rows->skip(1)->pluck('id')->all();
            if (!empty($duplicateIds)) {
                ItemStock::query()->whereIn('id', $duplicateIds)->delete();
            }
        }

        return $stock;
    }

    private static function syncSummary(
        int $companyId,
        int $warehouseId,
        int $itemId,
        ?int $variantId,
        float $balance,
        ?string $uom
    ): void {
        $query = StockSummary::query()
            ->where('company_id', $companyId)
            ->where('warehouse_id', $warehouseId)
            ->where('item_id', $itemId);
        static::applyVariantScope($query, $variantId, 'variant_id');

        $rows = $query
            ->lockForUpdate()
            ->orderBy('id')
            ->get();

        $summary = $rows->first();
        if (!$summary) {
            StockSummary::create([
                'company_id'   => $companyId,
                'warehouse_id' => $warehouseId,
                'item_id'      => $itemId,
                'variant_id'   => $variantId,
                'qty_balance'  => $balance,
                'uom'          => $uom,
            ]);
            return;
        }

        $summary->qty_balance = $balance;
        $summary->uom = $uom;
        $summary->save();

        $duplicateIds = $rows->skip(1)->pluck('id')->all();
        if (!empty($duplicateIds)) {
            StockSummary::query()->whereIn('id', $duplicateIds)->delete();
        }
    }

    private static function recomputeLedgerBalancesForScope(
        int $companyId,
        int $warehouseId,
        int $itemId,
        ?int $variantId,
        float $currentBalance
    ): void {
        $query = StockLedger::query()
            ->where('company_id', $companyId)
            ->where('warehouse_id', $warehouseId)
            ->where('item_id', $itemId);
        static::applyVariantScope($query, $variantId, 'item_variant_id');

        $rows = $query
            ->lockForUpdate()
            ->orderBy('ledger_date')
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $totalChange = (float) $rows->sum(fn (StockLedger $row) => (float) $row->qty_change);
        $runningBalance = (float) $currentBalance - $totalChange;

        foreach ($rows as $row) {
            $runningBalance += (float) $row->qty_change;
            if (abs(((float) $row->balance_after) - $runningBalance) < 0.000001) {
                continue;
            }
            $row->balance_after = $runningBalance;
            $row->save();
        }
    }

    private static function applyVariantScope(Builder $query, ?int $variantId, string $column): Builder
    {
        if ($variantId !== null) {
            return $query->where($column, $variantId);
        }

        return $query->whereNull($column);
    }

    private static function adjustDeliveredQuantity(DeliveryLine $line, float $delta): void
    {
        if (!$line->quotation_line_id || $delta === 0.0) {
            return;
        }

        $row = DB::table('quotation_lines')
            ->where('id', $line->quotation_line_id)
            ->lockForUpdate()
            ->first();

        if (!$row) {
            return;
        }

        $new = (float) ($row->qty_delivered ?? 0) + $delta;
        $maxQty = (float) ($row->qty ?? $new);
        $new = max(0, min($maxQty, $new));

        DB::table('quotation_lines')
            ->where('id', $line->quotation_line_id)
            ->update(['qty_delivered' => $new]);
    }

    private static function resolveUserId(?int $userId): ?int
    {
        if ($userId) {
            return $userId;
        }

        return auth()->id();
    }

    public static function postManufactureJob(ManufactureJob $job)
    {
        DB::transaction(function () use ($job) {
            // Loop komponen & kurangi stok
            foreach ($job->json_components as $c) {
                $component = Item::findOrFail($c['item_id']);
                $qty = $c['qty_used'];

                static::decreaseStock(
                    $component,
                    $qty,
                    referenceType: 'manufacture',
                    referenceId: $job->id,
                    notes: "Used in manufacture job #{$job->id}"
                );
            }

            // Tambah stok hasil produksi
            $item = Item::findOrFail($job->parent_item_id);
            static::increaseStock(
                $item,
                $job->qty_produced,
                referenceType: 'manufacture',
                referenceId: $job->id,
                notes: "Result of manufacture job #{$job->id}"
            );

            $job->update(['posted_at' => now()]);
        });
    }

}
