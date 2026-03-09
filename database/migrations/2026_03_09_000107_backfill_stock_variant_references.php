<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TOKEN_NULL = 'null';

    /** @var array<int, bool> */
    private array $variantExistsCache = [];

    /** @var array<string, int> */
    private array $deliveryVariantCache = [];

    /** @var array<string, int> */
    private array $adjustmentVariantCache = [];

    public function up(): void
    {
        if (!Schema::hasTable('stock_ledgers')) {
            return;
        }

        $scopeMoves = [];

        DB::table('stock_ledgers')
            ->select([
                'id',
                'company_id',
                'warehouse_id',
                'item_id',
                'item_variant_id',
                'reference_type',
                'reference_id',
                'qty_change',
            ])
            ->whereIn('reference_type', ['delivery', 'delivery_cancel', 'manual_adjustment'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$scopeMoves) {
                foreach ($rows as $row) {
                    $oldVariantId = $row->item_variant_id !== null ? (int) $row->item_variant_id : null;
                    $targetVariantId = $this->resolveLedgerVariantId($row, $oldVariantId);

                    if ($targetVariantId === null || $targetVariantId === $oldVariantId) {
                        continue;
                    }

                    DB::table('stock_ledgers')
                        ->where('id', (int) $row->id)
                        ->update([
                            'item_variant_id' => $targetVariantId,
                            'updated_at' => now(),
                        ]);

                    $scopeKey = $this->scopeKey((int) $row->company_id, (int) $row->warehouse_id, (int) $row->item_id);
                    $moveKey = $this->variantToken($oldVariantId).'->'.$this->variantToken($targetVariantId);

                    $scopeMoves[$scopeKey] ??= [];
                    $scopeMoves[$scopeKey][$moveKey] = (float) ($scopeMoves[$scopeKey][$moveKey] ?? 0)
                        + (float) ($row->qty_change ?? 0);
                }
            }, 'id');

        foreach ($scopeMoves as $scopeKey => $moves) {
            [$companyId, $warehouseId, $itemId] = array_map('intval', explode('|', $scopeKey));
            $this->rebalanceItemStocksScope($companyId, $warehouseId, $itemId, $moves);
            $this->rebalanceStockSummariesScope($companyId, $warehouseId, $itemId, $moves);
        }

        $this->consolidateItemStocksDuplicates();
        $this->consolidateStockSummariesDuplicates();
    }

    public function down(): void
    {
        // Data migration only; no rollback.
    }

    private function resolveLedgerVariantId(object $row, ?int $currentVariantId): ?int
    {
        if ($currentVariantId !== null && $this->variantExists($currentVariantId)) {
            return $currentVariantId;
        }

        $mappedVariantId = $this->resolveVariantFromReference(
            (string) ($row->reference_type ?? ''),
            $row->reference_id !== null ? (int) $row->reference_id : null,
            (int) $row->item_id
        );

        if ($mappedVariantId !== null && $this->variantExists($mappedVariantId)) {
            return $mappedVariantId;
        }

        // Keep deleted marker when not resolvable deterministically.
        return null;
    }

    private function resolveVariantFromReference(string $referenceType, ?int $referenceId, int $itemId): ?int
    {
        if ($referenceId === null || $referenceId <= 0) {
            return null;
        }

        if (in_array($referenceType, ['delivery', 'delivery_cancel'], true)) {
            if (!Schema::hasTable('delivery_lines')) {
                return null;
            }

            $cacheKey = $referenceId.'|'.$itemId;
            if (!array_key_exists($cacheKey, $this->deliveryVariantCache)) {
                $variantIds = DB::table('delivery_lines')
                    ->where('delivery_id', $referenceId)
                    ->where('item_id', $itemId)
                    ->whereNotNull('item_variant_id')
                    ->distinct()
                    ->pluck('item_variant_id')
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0)
                    ->values();

                $this->deliveryVariantCache[$cacheKey] = $variantIds->count() === 1
                    ? (int) $variantIds->first()
                    : 0;
            }

            $candidate = (int) $this->deliveryVariantCache[$cacheKey];
            return $candidate > 0 ? $candidate : null;
        }

        if ($referenceType === 'manual_adjustment') {
            if (!Schema::hasTable('stock_adjustments')) {
                return null;
            }

            $cacheKey = $referenceId.'|'.$itemId;
            if (!array_key_exists($cacheKey, $this->adjustmentVariantCache)) {
                $variantId = DB::table('stock_adjustments')
                    ->where('id', $referenceId)
                    ->where('item_id', $itemId)
                    ->value('variant_id');

                $variantId = $variantId !== null ? (int) $variantId : 0;
                $this->adjustmentVariantCache[$cacheKey] = $variantId > 0 ? $variantId : 0;
            }

            $candidate = (int) $this->adjustmentVariantCache[$cacheKey];
            return $candidate > 0 ? $candidate : null;
        }

        return null;
    }

    private function rebalanceItemStocksScope(int $companyId, int $warehouseId, int $itemId, array $moves): void
    {
        if (!Schema::hasTable('item_stocks') || empty($moves)) {
            return;
        }

        DB::transaction(function () use ($companyId, $warehouseId, $itemId, $moves) {
            $rows = DB::table('item_stocks')
                ->where('company_id', $companyId)
                ->where('warehouse_id', $warehouseId)
                ->where('item_id', $itemId)
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            $balances = [];
            $bucketRows = [];

            foreach ($rows as $row) {
                $token = $this->variantToken($row->item_variant_id !== null ? (int) $row->item_variant_id : null);
                $balances[$token] = (float) ($balances[$token] ?? 0) + (float) ($row->qty_on_hand ?? 0);
                $bucketRows[$token] ??= [];
                $bucketRows[$token][] = $row;
            }

            foreach ($moves as $moveKey => $delta) {
                [$fromToken, $toToken] = explode('->', $moveKey, 2);
                $qty = (float) $delta;
                if (abs($qty) < 0.0000001) {
                    continue;
                }
                $balances[$fromToken] = (float) ($balances[$fromToken] ?? 0) - $qty;
                $balances[$toToken] = (float) ($balances[$toToken] ?? 0) + $qty;
            }

            $now = now();
            foreach ($balances as $token => $balance) {
                $primary = $bucketRows[$token][0] ?? null;
                $duplicates = array_slice($bucketRows[$token] ?? [], 1);

                if ($primary) {
                    DB::table('item_stocks')
                        ->where('id', (int) $primary->id)
                        ->update([
                            'qty_on_hand' => $balance,
                            'updated_at' => $now,
                        ]);
                    if (!empty($duplicates)) {
                        DB::table('item_stocks')
                            ->whereIn('id', array_map(fn ($r) => (int) $r->id, $duplicates))
                            ->delete();
                    }
                    continue;
                }

                if (abs($balance) < 0.0000001) {
                    continue;
                }

                DB::table('item_stocks')->insert([
                    'company_id' => $companyId,
                    'warehouse_id' => $warehouseId,
                    'item_id' => $itemId,
                    'item_variant_id' => $this->variantFromToken($token),
                    'qty_on_hand' => $balance,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    private function rebalanceStockSummariesScope(int $companyId, int $warehouseId, int $itemId, array $moves): void
    {
        if (!Schema::hasTable('stock_summaries') || empty($moves)) {
            return;
        }

        DB::transaction(function () use ($companyId, $warehouseId, $itemId, $moves) {
            $rows = DB::table('stock_summaries')
                ->where('company_id', $companyId)
                ->where('warehouse_id', $warehouseId)
                ->where('item_id', $itemId)
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            $balances = [];
            $bucketRows = [];
            $uom = null;

            foreach ($rows as $row) {
                $token = $this->variantToken($row->variant_id !== null ? (int) $row->variant_id : null);
                $balances[$token] = (float) ($balances[$token] ?? 0) + (float) ($row->qty_balance ?? 0);
                $bucketRows[$token] ??= [];
                $bucketRows[$token][] = $row;

                if ($uom === null && !empty($row->uom)) {
                    $uom = (string) $row->uom;
                }
            }

            foreach ($moves as $moveKey => $delta) {
                [$fromToken, $toToken] = explode('->', $moveKey, 2);
                $qty = (float) $delta;
                if (abs($qty) < 0.0000001) {
                    continue;
                }
                $balances[$fromToken] = (float) ($balances[$fromToken] ?? 0) - $qty;
                $balances[$toToken] = (float) ($balances[$toToken] ?? 0) + $qty;
            }

            $now = now();
            foreach ($balances as $token => $balance) {
                $primary = $bucketRows[$token][0] ?? null;
                $duplicates = array_slice($bucketRows[$token] ?? [], 1);

                if ($primary) {
                    DB::table('stock_summaries')
                        ->where('id', (int) $primary->id)
                        ->update([
                            'qty_balance' => $balance,
                            'uom' => $primary->uom ?? $uom,
                            'updated_at' => $now,
                        ]);
                    if (!empty($duplicates)) {
                        DB::table('stock_summaries')
                            ->whereIn('id', array_map(fn ($r) => (int) $r->id, $duplicates))
                            ->delete();
                    }
                    continue;
                }

                if (abs($balance) < 0.0000001) {
                    continue;
                }

                DB::table('stock_summaries')->insert([
                    'company_id' => $companyId,
                    'warehouse_id' => $warehouseId,
                    'item_id' => $itemId,
                    'variant_id' => $this->variantFromToken($token),
                    'qty_balance' => $balance,
                    'uom' => $uom,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    private function consolidateItemStocksDuplicates(): void
    {
        if (!Schema::hasTable('item_stocks')) {
            return;
        }

        $groups = DB::table('item_stocks')
            ->select([
                'company_id',
                'warehouse_id',
                'item_id',
                DB::raw('COALESCE(item_variant_id, 0) as variant_scope'),
                DB::raw('COUNT(*) as row_count'),
            ])
            ->groupBy('company_id', 'warehouse_id', 'item_id', DB::raw('COALESCE(item_variant_id, 0)'))
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $group) {
            DB::transaction(function () use ($group) {
                $query = DB::table('item_stocks')
                    ->where('company_id', (int) $group->company_id)
                    ->where('warehouse_id', (int) $group->warehouse_id)
                    ->where('item_id', (int) $group->item_id);

                if ((int) $group->variant_scope > 0) {
                    $query->where('item_variant_id', (int) $group->variant_scope);
                } else {
                    $query->whereNull('item_variant_id');
                }

                $rows = $query->lockForUpdate()->orderBy('id')->get();
                if ($rows->count() < 2) {
                    return;
                }

                $first = $rows->first();
                $total = (float) $rows->sum(fn ($row) => (float) ($row->qty_on_hand ?? 0));
                DB::table('item_stocks')->where('id', (int) $first->id)->update([
                    'qty_on_hand' => $total,
                    'updated_at' => now(),
                ]);

                $duplicateIds = $rows->skip(1)->pluck('id')->map(fn ($id) => (int) $id)->all();
                if (!empty($duplicateIds)) {
                    DB::table('item_stocks')->whereIn('id', $duplicateIds)->delete();
                }
            });
        }
    }

    private function consolidateStockSummariesDuplicates(): void
    {
        if (!Schema::hasTable('stock_summaries')) {
            return;
        }

        $groups = DB::table('stock_summaries')
            ->select([
                'company_id',
                'warehouse_id',
                'item_id',
                DB::raw('COALESCE(variant_id, 0) as variant_scope'),
                DB::raw('COUNT(*) as row_count'),
            ])
            ->groupBy('company_id', 'warehouse_id', 'item_id', DB::raw('COALESCE(variant_id, 0)'))
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $group) {
            DB::transaction(function () use ($group) {
                $query = DB::table('stock_summaries')
                    ->where('company_id', (int) $group->company_id)
                    ->where('warehouse_id', (int) $group->warehouse_id)
                    ->where('item_id', (int) $group->item_id);

                if ((int) $group->variant_scope > 0) {
                    $query->where('variant_id', (int) $group->variant_scope);
                } else {
                    $query->whereNull('variant_id');
                }

                $rows = $query->lockForUpdate()->orderBy('id')->get();
                if ($rows->count() < 2) {
                    return;
                }

                $first = $rows->first();
                $total = (float) $rows->sum(fn ($row) => (float) ($row->qty_balance ?? 0));
                DB::table('stock_summaries')->where('id', (int) $first->id)->update([
                    'qty_balance' => $total,
                    'uom' => $first->uom,
                    'updated_at' => now(),
                ]);

                $duplicateIds = $rows->skip(1)->pluck('id')->map(fn ($id) => (int) $id)->all();
                if (!empty($duplicateIds)) {
                    DB::table('stock_summaries')->whereIn('id', $duplicateIds)->delete();
                }
            });
        }
    }

    private function scopeKey(int $companyId, int $warehouseId, int $itemId): string
    {
        return $companyId.'|'.$warehouseId.'|'.$itemId;
    }

    private function variantToken(?int $variantId): string
    {
        return $variantId === null ? self::TOKEN_NULL : (string) $variantId;
    }

    private function variantFromToken(string $token): ?int
    {
        return $token === self::TOKEN_NULL ? null : (int) $token;
    }

    private function variantExists(int $variantId): bool
    {
        if ($variantId <= 0) {
            return false;
        }

        if (!array_key_exists($variantId, $this->variantExistsCache)) {
            $this->variantExistsCache[$variantId] = DB::table('item_variants')
                ->where('id', $variantId)
                ->exists();
        }

        return $this->variantExistsCache[$variantId];
    }
};
