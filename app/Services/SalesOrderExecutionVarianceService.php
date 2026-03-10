<?php

namespace App\Services;

use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SalesOrderExecutionVarianceService
{
    /**
     * @return array{
     *   rows: array<int, array<string,mixed>>,
     *   totals: array<string,float>
     * }
     */
    public function build(SalesOrder $salesOrder): array
    {
        $salesOrder->loadMissing('lines');
        if ($salesOrder->lines->isEmpty()) {
            return [
                'rows' => [],
                'totals' => [
                    'planned_qty' => 0.0,
                    'po_ordered_qty' => 0.0,
                    'gr_received_qty' => 0.0,
                    'delivered_qty' => 0.0,
                    'ordered_variance_qty' => 0.0,
                    'delivered_variance_qty' => 0.0,
                ],
            ];
        }

        $lineIds = $salesOrder->lines->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $rows = [];
        $lineKeyToId = [];
        $lineKeyCount = [];
        foreach ($salesOrder->lines as $line) {
            $lineId = (int) $line->id;
            $key = $this->stockKey((int) ($line->item_id ?? 0), (int) ($line->item_variant_id ?? 0));
            if ($key !== null) {
                $lineKeyCount[$key] = ($lineKeyCount[$key] ?? 0) + 1;
                $lineKeyToId[$key] = $lineId;
            }

            $planned = (float) ($line->qty_ordered ?? 0);
            $delivered = (float) ($line->qty_delivered ?? 0);
            $rows[$lineId] = [
                'line_id' => $lineId,
                'line_name' => (string) ($line->name ?? ''),
                'line_description' => (string) ($line->description ?? ''),
                'planned_qty' => $planned,
                'po_ordered_qty' => 0.0,
                'gr_received_qty' => 0.0,
                'delivered_qty' => $delivered,
                'ordered_variance_qty' => 0.0,
                'delivered_variance_qty' => 0.0,
            ];
        }

        $poStatsByLine = [];
        if (Schema::hasTable('purchase_order_lines') && Schema::hasColumn('purchase_order_lines', 'sales_order_line_id')) {
            $poStatsByLine = DB::table('purchase_order_lines')
                ->selectRaw('sales_order_line_id, SUM(COALESCE(qty_ordered,0)) as po_ordered_qty, SUM(COALESCE(qty_received,0)) as gr_received_qty')
                ->whereIn('sales_order_line_id', $lineIds)
                ->groupBy('sales_order_line_id')
                ->get()
                ->keyBy(fn ($row) => (int) $row->sales_order_line_id)
                ->all();
        }

        foreach ($poStatsByLine as $lineId => $row) {
            if (!isset($rows[$lineId])) {
                continue;
            }
            $rows[$lineId]['po_ordered_qty'] = (float) ($row->po_ordered_qty ?? 0);
            $rows[$lineId]['gr_received_qty'] = (float) ($row->gr_received_qty ?? 0);
        }

        // Fallback read-only for legacy PO lines without explicit sales_order_line_id.
        $uniqueKeys = array_keys(array_filter($lineKeyCount, fn ($count) => (int) $count === 1));
        if (
            !empty($uniqueKeys)
            && Schema::hasTable('purchase_order_lines')
            && Schema::hasColumn('purchase_order_lines', 'item_id')
            && Schema::hasColumn('purchase_order_lines', 'item_variant_id')
            && Schema::hasColumn('purchase_order_lines', 'sales_order_line_id')
        ) {
            $itemIds = [];
            foreach ($uniqueKeys as $key) {
                [$itemId, $variantId] = explode('|', $key);
                $itemIds[] = (int) $itemId;
            }
            $legacyRows = DB::table('purchase_order_lines')
                ->selectRaw('item_id, item_variant_id, SUM(COALESCE(qty_ordered,0)) as po_ordered_qty, SUM(COALESCE(qty_received,0)) as gr_received_qty')
                ->whereNull('sales_order_line_id')
                ->whereIn('item_id', array_unique($itemIds))
                ->groupBy('item_id', 'item_variant_id')
                ->get();

            foreach ($legacyRows as $legacy) {
                $key = $this->stockKey((int) ($legacy->item_id ?? 0), (int) ($legacy->item_variant_id ?? 0));
                if ($key === null || !isset($lineKeyToId[$key]) || (($lineKeyCount[$key] ?? 0) !== 1)) {
                    continue;
                }
                $lineId = (int) $lineKeyToId[$key];
                if (!isset($rows[$lineId])) {
                    continue;
                }
                $rows[$lineId]['po_ordered_qty'] += (float) ($legacy->po_ordered_qty ?? 0);
                $rows[$lineId]['gr_received_qty'] += (float) ($legacy->gr_received_qty ?? 0);
            }
        }

        $totals = [
            'planned_qty' => 0.0,
            'po_ordered_qty' => 0.0,
            'gr_received_qty' => 0.0,
            'delivered_qty' => 0.0,
            'ordered_variance_qty' => 0.0,
            'delivered_variance_qty' => 0.0,
        ];

        foreach ($rows as $lineId => $row) {
            $orderedVariance = (float) $row['po_ordered_qty'] - (float) $row['planned_qty'];
            $deliveredVariance = (float) $row['delivered_qty'] - (float) $row['planned_qty'];
            $rows[$lineId]['ordered_variance_qty'] = $orderedVariance;
            $rows[$lineId]['delivered_variance_qty'] = $deliveredVariance;
            $rows[$lineId]['ordered_variance_state'] = $this->varianceState($orderedVariance);
            $rows[$lineId]['delivered_variance_state'] = $this->varianceState($deliveredVariance);

            $totals['planned_qty'] += (float) $row['planned_qty'];
            $totals['po_ordered_qty'] += (float) $row['po_ordered_qty'];
            $totals['gr_received_qty'] += (float) $row['gr_received_qty'];
            $totals['delivered_qty'] += (float) $row['delivered_qty'];
            $totals['ordered_variance_qty'] += $orderedVariance;
            $totals['delivered_variance_qty'] += $deliveredVariance;
        }

        return [
            'rows' => array_values($rows),
            'totals' => $totals,
        ];
    }

    private function varianceState(float $value): string
    {
        if (abs($value) < 0.0001) {
            return 'balanced';
        }

        return $value > 0 ? 'excess' : 'short';
    }

    private function stockKey(int $itemId, int $variantId): ?string
    {
        if ($itemId <= 0) {
            return null;
        }

        return $itemId.'|'.$variantId;
    }
}

