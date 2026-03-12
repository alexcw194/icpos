<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\DeliveryLine;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\ManufactureJob;
use App\Models\ManufactureRecipe;
use App\Models\StockAdjustment;
use App\Models\StockLedger;
use App\Models\StockSummary;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StockService
{
    private const KIT_ITEM_TYPES = ['kit', 'bundle'];

    private static function adjustSalesOrderDelivered(DeliveryLine $line, float $delta): void
    {
        if (!$line->sales_order_line_id || $delta === 0.0) {
            return;
        }

        $soLine = DB::table('sales_order_lines')
            ->where('id', $line->sales_order_line_id)
            ->lockForUpdate()
            ->first();
        if (!$soLine) {
            return;
        }

        $newDelivered = max(0, (float) ($soLine->qty_delivered ?? 0) + $delta);

        DB::table('sales_order_lines')
            ->where('id', $soLine->id)
            ->update(['qty_delivered' => $newDelivered]);

        if ($line->quotation_line_id ?? null) {
            DB::table('quotation_lines')
                ->where('id', $line->quotation_line_id)
                ->update(['qty_delivered' => $newDelivered]);
        }
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
                ->with(['lines.item', 'warehouse', 'company'])
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
                if (static::isKitLine($line)) {
                    static::autoManufactureForDeliveryLine(
                        delivery: $lockedDelivery,
                        line: $line,
                        warehouse: $warehouse,
                        userId: $userId,
                        movementDate: $movementDate
                    );
                }

                static::deductStock(
                    companyId: (int) $lockedDelivery->company_id,
                    warehouseId: (int) $warehouse->id,
                    itemId: $line->item_id,
                    variantId: $line->item_variant_id,
                    qty: (float) $line->qty,
                    allowNegative: (bool) $warehouse->allow_negative_stock,
                    referenceType: 'delivery',
                    referenceId: (int) $lockedDelivery->id,
                    userId: $userId,
                    date: $movementDate
                );

                static::adjustDeliveredQuantity($line, (float) $line->qty);
                static::adjustSalesOrderDelivered($line, (float) $line->qty);
            }

            $lockedDelivery->forceFill([
                'number' => $lockedDelivery->number,
                'status' => Delivery::STATUS_POSTED,
                'posted_at' => $timestamp,
                'posted_by' => $userId,
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

    public static function cancelDelivery(
        Delivery $delivery,
        ?int $actingUserId = null,
        ?string $reason = null,
        bool $reverseAutoManufacture = false
    ): void {
        if ($delivery->status === Delivery::STATUS_CANCELLED) {
            return;
        }

        DB::transaction(function () use ($delivery, $actingUserId, $reason, $reverseAutoManufacture) {
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
                        companyId: (int) $lockedDelivery->company_id,
                        warehouseId: (int) $warehouse->id,
                        itemId: $line->item_id,
                        variantId: $line->item_variant_id,
                        qty: (float) $line->qty,
                        referenceType: 'delivery_cancel',
                        referenceId: (int) $lockedDelivery->id,
                        userId: $userId,
                        date: $movementDate
                    );

                    static::adjustDeliveredQuantity($line, -1 * (float) $line->qty);
                    static::adjustSalesOrderDelivered($line, -1 * (float) $line->qty);
                }

                if ($reverseAutoManufacture) {
                    static::reverseAutoManufactureJobsForDelivery(
                        delivery: $lockedDelivery,
                        userId: $userId,
                        movementDate: $movementDate,
                        reason: $reason
                    );
                }
            }

            $lockedDelivery->forceFill([
                'status' => Delivery::STATUS_CANCELLED,
                'cancelled_at' => $timestamp,
                'cancelled_by' => $userId,
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
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
                'item_id' => $itemId,
                'item_variant_id' => $variantId,
                'ledger_date' => $movementDate,
                'qty_change' => (float) $qtyAdjustment,
                'balance_after' => $balance,
                'reference_type' => 'manual_adjustment',
                'reference_id' => $referenceId,
                'created_by' => $userId,
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
        DB::transaction(function () use ($adjustment) {
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

    /**
     * @return array<string,string>
     */
    public static function collectDeliveryPreflightErrors(Delivery $delivery): array
    {
        $delivery->loadMissing(['lines.item', 'warehouse']);

        $warehouse = $delivery->warehouse;
        if (!$warehouse) {
            return ['delivery' => 'Warehouse belum dipilih.'];
        }

        $errors = [];
        foreach ($delivery->lines as $i => $line) {
            try {
                static::assertSufficientStockForLine($warehouse, $line);
            } catch (RuntimeException $e) {
                $errors["lines.$i.qty"] = $e->getMessage();
            }
        }

        return $errors;
    }

    public static function ensureSufficientStock(Warehouse $warehouse, EloquentCollection $lines): void
    {
        foreach ($lines as $line) {
            if (!$line instanceof DeliveryLine) {
                continue;
            }
            static::assertSufficientStockForLine($warehouse, $line);
        }
    }

    private static function assertSufficientStockForLine(Warehouse $warehouse, DeliveryLine $line): void
    {
        if (!$line->item_id || (float) $line->qty <= 0) {
            return;
        }

        if (static::isKitLine($line)) {
            $components = static::buildKitComponentsForLine($line);
            foreach ($components as $component) {
                $need = (float) $component['qty_used'];
                $stockQuery = ItemStock::query()
                    ->where('company_id', (int) $warehouse->company_id)
                    ->where('warehouse_id', (int) $warehouse->id)
                    ->where('item_id', (int) $component['item_id']);
                static::applyVariantScope($stockQuery, $component['component_variant_id'], 'item_variant_id');

                $available = (float) $stockQuery->sum('qty_on_hand');

                if (!$warehouse->allow_negative_stock && ($available + 1e-9) < $need) {
                    $componentName = Item::query()->whereKey((int) $component['item_id'])->value('name')
                        ?? ('#' . (int) $component['item_id']);
                    $deficit = max(0, round($need - $available, 3));
                    throw new RuntimeException(
                        "Stok komponen {$componentName} kurang {$deficit} untuk produksi kit."
                    );
                }
            }

            return;
        }

        $stockQuery = ItemStock::query()
            ->where('company_id', (int) $warehouse->company_id)
            ->where('warehouse_id', (int) $warehouse->id)
            ->where('item_id', (int) $line->item_id);
        static::applyVariantScope($stockQuery, $line->item_variant_id, 'item_variant_id');

        $available = (float) $stockQuery->sum('qty_on_hand');
        if (!$warehouse->allow_negative_stock && ($available + 1e-9) < (float) $line->qty) {
            $name = $line->description ?: ($line->item?->name ?: ('#' . (int) $line->item_id));
            $deficit = max(0, round((float) $line->qty - $available, 3));
            throw new RuntimeException(
                "Stok kurang {$deficit} untuk {$name} (tersedia {$available}, diminta " . (float) $line->qty . ').'
            );
        }
    }

    /**
     * @return array<int,array<string,int|float|null>>
     */
    private static function buildKitComponentsForLine(DeliveryLine $line): array
    {
        $parentItem = static::resolveLineItem($line);
        if (!$parentItem || !static::isKitItem($parentItem)) {
            return [];
        }

        $recipes = ManufactureRecipe::query()
            ->where('parent_item_id', (int) $line->item_id)
            ->with(['componentVariant:id,item_id'])
            ->orderBy('id')
            ->get();

        if ($recipes->isEmpty()) {
            throw new RuntimeException('Belum ada recipe untuk item kit/bundle ini.');
        }

        $components = [];
        $qtyProduced = (float) $line->qty;

        foreach ($recipes as $recipe) {
            $componentItemId = $recipe->component_item_id ?: ($recipe->componentVariant?->item_id);
            if (!$componentItemId) {
                throw new RuntimeException('Data recipe invalid: komponen tidak memiliki item/variant.');
            }

            if ((int) $componentItemId === (int) $line->item_id) {
                throw new RuntimeException('Data recipe invalid: komponen tidak boleh sama dengan item hasil.');
            }

            $qtyUsed = (float) $recipe->qty_required * $qtyProduced;
            if ($qtyUsed <= 0) {
                continue;
            }

            $components[] = [
                'item_id' => (int) $componentItemId,
                'component_variant_id' => $recipe->component_variant_id ? (int) $recipe->component_variant_id : null,
                'qty_used' => $qtyUsed,
                'recipe_id' => (int) $recipe->id,
            ];
        }

        if (empty($components)) {
            throw new RuntimeException('Recipe kit tidak memiliki komponen dengan qty valid.');
        }

        return $components;
    }

    private static function autoManufactureForDeliveryLine(
        Delivery $delivery,
        DeliveryLine $line,
        Warehouse $warehouse,
        ?int $userId,
        Carbon $movementDate
    ): ?ManufactureJob {
        if (!static::isKitLine($line)) {
            return null;
        }

        $components = static::buildKitComponentsForLine($line);

        $deliveryNumber = $delivery->number ?: ('#' . $delivery->id);
        $job = ManufactureJob::create([
            'parent_item_id' => (int) $line->item_id,
            'qty_produced' => (float) $line->qty,
            'job_type' => 'production',
            'json_components' => $components,
            'produced_by' => $userId,
            'produced_at' => $movementDate,
            'posted_at' => Carbon::now(),
            'notes' => 'Auto production from delivery ' . $deliveryNumber . ' line #' . $line->id,
            'source_type' => 'delivery',
            'source_id' => (int) $delivery->id,
            'source_line_id' => (int) $line->id,
            'is_auto' => true,
        ]);

        foreach ($components as $component) {
            static::deductStock(
                companyId: (int) $delivery->company_id,
                warehouseId: (int) $warehouse->id,
                itemId: (int) $component['item_id'],
                variantId: $component['component_variant_id'],
                qty: (float) $component['qty_used'],
                allowNegative: (bool) $warehouse->allow_negative_stock,
                referenceType: 'manufacture',
                referenceId: (int) $job->id,
                userId: $userId,
                date: $movementDate
            );
        }

        static::addStock(
            companyId: (int) $delivery->company_id,
            warehouseId: (int) $warehouse->id,
            itemId: (int) $line->item_id,
            variantId: $line->item_variant_id ? (int) $line->item_variant_id : null,
            qty: (float) $line->qty,
            referenceType: 'manufacture',
            referenceId: (int) $job->id,
            userId: $userId,
            date: $movementDate
        );

        return $job;
    }

    private static function reverseAutoManufactureJobsForDelivery(
        Delivery $delivery,
        ?int $userId,
        Carbon $movementDate,
        ?string $reason = null
    ): void {
        if (!$delivery->warehouse_id) {
            throw new RuntimeException('Warehouse is required to reverse auto manufacture jobs.');
        }

        $jobs = ManufactureJob::query()
            ->where('source_type', 'delivery')
            ->where('source_id', (int) $delivery->id)
            ->where('is_auto', true)
            ->whereNull('reversed_at')
            ->lockForUpdate()
            ->orderBy('id')
            ->get();

        if ($jobs->isEmpty()) {
            return;
        }

        $lineVariantMap = DeliveryLine::query()
            ->whereIn('id', $jobs->pluck('source_line_id')->filter()->map(fn ($id) => (int) $id)->all())
            ->pluck('item_variant_id', 'id')
            ->map(fn ($value) => $value ? (int) $value : null);

        foreach ($jobs as $job) {
            $parentVariantId = $job->source_line_id ? ($lineVariantMap[(int) $job->source_line_id] ?? null) : null;

            static::deductStock(
                companyId: (int) $delivery->company_id,
                warehouseId: (int) $delivery->warehouse_id,
                itemId: (int) $job->parent_item_id,
                variantId: $parentVariantId,
                qty: (float) $job->qty_produced,
                allowNegative: true,
                referenceType: 'manufacture_reverse',
                referenceId: (int) $job->id,
                userId: $userId,
                date: $movementDate
            );

            foreach ((array) $job->json_components as $component) {
                $componentItemId = (int) ($component['item_id'] ?? 0);
                $qtyUsed = (float) ($component['qty_used'] ?? 0);
                $componentVariantId = isset($component['component_variant_id'])
                    ? ((int) $component['component_variant_id'] ?: null)
                    : null;

                if ($componentItemId <= 0 || $qtyUsed <= 0) {
                    continue;
                }

                static::addStock(
                    companyId: (int) $delivery->company_id,
                    warehouseId: (int) $delivery->warehouse_id,
                    itemId: $componentItemId,
                    variantId: $componentVariantId,
                    qty: $qtyUsed,
                    referenceType: 'manufacture_reverse',
                    referenceId: (int) $job->id,
                    userId: $userId,
                    date: $movementDate
                );
            }

            $job->forceFill([
                'reversed_at' => Carbon::now(),
                'reversed_by' => $userId,
                'reversal_notes' => $reason,
            ])->save();
        }
    }

    private static function isKitLine(DeliveryLine $line): bool
    {
        $item = static::resolveLineItem($line);
        return static::isKitItem($item);
    }

    private static function resolveLineItem(DeliveryLine $line): ?Item
    {
        if ($line->relationLoaded('item')) {
            return $line->item;
        }

        if (!$line->item_id) {
            return null;
        }

        return Item::query()->find((int) $line->item_id);
    }

    private static function isKitItem(?Item $item): bool
    {
        if (!$item) {
            return false;
        }

        return in_array((string) $item->item_type, self::KIT_ITEM_TYPES, true);
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
        if (!$itemId || $qty <= 0) {
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
            'company_id' => $companyId,
            'warehouse_id' => $warehouseId,
            'item_id' => $itemId,
            'item_variant_id' => $variantId,
            'ledger_date' => $date,
            'qty_change' => -abs($qty),
            'balance_after' => $balance,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'created_by' => $userId,
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
        if (!$itemId || $qty <= 0) {
            return;
        }

        $stock = static::getStockForUpdate($companyId, $warehouseId, $itemId, $variantId);

        $balance = (float) $stock->qty_on_hand + $qty;
        $stock->qty_on_hand = $balance;
        $stock->save();

        StockLedger::create([
            'company_id' => $companyId,
            'warehouse_id' => $warehouseId,
            'item_id' => $itemId,
            'item_variant_id' => $variantId,
            'ledger_date' => $date,
            'qty_change' => abs($qty),
            'balance_after' => $balance,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'created_by' => $userId,
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
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
                'item_id' => $itemId,
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
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
                'item_id' => $itemId,
                'variant_id' => $variantId,
                'qty_balance' => $balance,
                'uom' => $uom,
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

    public static function postManufactureJob(ManufactureJob $job): void
    {
        if ($job->posted_at) {
            return;
        }

        $job->forceFill([
            'posted_at' => Carbon::now(),
        ])->save();
    }
}
