<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\DeliveryLine;
use App\Models\ItemStock;
use App\Models\StockLedger;
use App\Models\Warehouse;
use App\Models\SalesOrder;
use App\Services\DocNumberService;
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

        // Recompute SO status (open/partial_delivered/delivered)
        $agg = DB::table('sales_order_lines')
            ->selectRaw('SUM(qty) as ordered, SUM(qty_delivered) as delivered')
            ->where('sales_order_id', $soLine->sales_order_id)
            ->first();

        $status = 'open';
        if ($agg && (float)$agg->delivered > 0) {
            $status = ((float)$agg->delivered + 1e-9 >= (float)$agg->ordered) ? 'delivered' : 'partial_delivered';
        }

        DB::table('sales_orders')
            ->where('id', $soLine->sales_order_id)
            ->update(['status' => $status]);
    }

    /**
     * Perbarui status Sales Order menjadi `delivered`, `partial_delivered`, atau `open`.
     * - `delivered` jika semua baris sudah terkirim penuh (qty_delivered >= qty_ordered)
     * - `partial_delivered` jika ada sebagian baris terkirim tetapi belum semuanya
     * - `open` jika belum ada qty_delivered sama sekali
     */
    private static function updateSalesOrderStatus(int $salesOrderId): void
    {
        $stats = DB::table('sales_order_lines')
            ->where('sales_order_id', $salesOrderId)
            ->selectRaw(
                'SUM(CASE WHEN qty_delivered >= qty_ordered THEN 1 ELSE 0 END) as delivered_lines, ' .
                'SUM(CASE WHEN qty_delivered > 0 THEN 1 ELSE 0 END) as partially_delivered_lines, ' .
                'COUNT(*) as total_lines'
            )
            ->first();

        if (!$stats || $stats->total_lines <= 0) {
            return;
        }

        $status = 'open';
        if ($stats->delivered_lines == $stats->total_lines) {
            $status = 'delivered';
        } elseif ($stats->partially_delivered_lines > 0) {
            $status = 'partial_delivered';
        }

        DB::table('sales_orders')->where('id', $salesOrderId)->update(['status' => $status]);
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
        });

        $delivery->refresh();
    }

    public static function ensureSufficientStock(Warehouse $warehouse, EloquentCollection $lines): void
    {
        $lines->each(function (DeliveryLine $line) use ($warehouse) {
            if (!$line->item_id) {
                return;
            }

            $stock = ItemStock::query()->firstOrNew([
                'company_id'      => $warehouse->company_id,
                'warehouse_id'    => $warehouse->id,
                'item_id'         => $line->item_id,
                'item_variant_id' => $line->item_variant_id,
            ]);

            $available = (float) $stock->qty_on_hand;
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
    }

    private static function getStockForUpdate(int $companyId, int $warehouseId, int $itemId, ?int $variantId): ItemStock
    {
        $stock = ItemStock::query()
            ->where('company_id', $companyId)
            ->where('warehouse_id', $warehouseId)
            ->where('item_id', $itemId)
            ->where('item_variant_id', $variantId)
            ->lockForUpdate()
            ->first();

        if (!$stock) {
            $stock = new ItemStock([
                'company_id'      => $companyId,
                'warehouse_id'    => $warehouseId,
                'item_id'         => $itemId,
                'item_variant_id' => $variantId,
            ]);
            $stock->qty_on_hand = 0;
        }

        return $stock;
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
}
