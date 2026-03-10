<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            !Schema::hasTable('sales_orders')
            || !Schema::hasTable('sales_order_lines')
            || !Schema::hasTable('project_quotation_lines')
        ) {
            return;
        }

        $requiredCols = [
            ['sales_orders', 'po_type'],
            ['sales_order_lines', 'baseline_project_quotation_line_id'],
            ['sales_order_lines', 'qty_ordered'],
            ['sales_order_lines', 'unit_price'],
            ['sales_order_lines', 'labor_unit'],
            ['sales_order_lines', 'material_total'],
            ['sales_order_lines', 'labor_total'],
            ['sales_order_lines', 'line_subtotal'],
            ['sales_order_lines', 'line_total'],
            ['sales_order_lines', 'discount_amount'],
            ['project_quotation_lines', 'labor_unit_cost_snapshot'],
        ];

        foreach ($requiredCols as [$table, $column]) {
            if (!Schema::hasColumn($table, $column)) {
                return;
            }
        }

        $lastId = 0;
        $chunkSize = 500;

        while (true) {
            $rows = DB::table('sales_order_lines as sol')
                ->join('sales_orders as so', 'so.id', '=', 'sol.sales_order_id')
                ->leftJoin('project_quotation_lines as pql', 'pql.id', '=', 'sol.baseline_project_quotation_line_id')
                ->where('so.po_type', 'project')
                ->where('sol.id', '>', $lastId)
                ->orderBy('sol.id')
                ->limit($chunkSize)
                ->get([
                    'sol.id',
                    'sol.qty_ordered',
                    'sol.unit_price',
                    'sol.labor_unit',
                    'sol.material_total',
                    'sol.labor_total',
                    'sol.line_subtotal',
                    'sol.line_total',
                    'sol.discount_amount',
                    'pql.labor_unit_cost_snapshot as baseline_labor_unit',
                ]);

            if ($rows->isEmpty()) {
                break;
            }

            foreach ($rows as $row) {
                $lastId = (int) $row->id;

                $qty = max((float) ($row->qty_ordered ?? 0), 0);
                if ($qty <= 0) {
                    continue;
                }

                $existingLaborUnit = max((float) ($row->labor_unit ?? 0), 0);
                $existingLaborTotal = max((float) ($row->labor_total ?? 0), 0);
                $baselineLaborUnit = max((float) ($row->baseline_labor_unit ?? 0), 0);
                if ($existingLaborUnit > 0 || $existingLaborTotal > 0 || $baselineLaborUnit <= 0) {
                    continue;
                }

                $sourceSubtotal = max((float) ($row->line_subtotal ?? 0), 0);
                if ($sourceSubtotal <= 0) {
                    $sourceSubtotal = max((float) ($row->line_total ?? 0), 0) + max((float) ($row->discount_amount ?? 0), 0);
                }
                if ($sourceSubtotal <= 0) {
                    $sourceSubtotal = max((float) ($row->material_total ?? 0), 0) + max((float) ($row->labor_total ?? 0), 0);
                }
                if ($sourceSubtotal <= 0) {
                    continue;
                }

                $laborUnit = round($baselineLaborUnit, 2);
                $laborTotal = round($qty * $laborUnit, 2);
                $materialTotal = round(max($sourceSubtotal - $laborTotal, 0), 2);
                $materialUnit = $qty > 0 ? round($materialTotal / $qty, 2) : max((float) ($row->unit_price ?? 0), 0);
                $lineSubtotal = round($materialTotal + $laborTotal, 2);
                $discountAmount = min(max((float) ($row->discount_amount ?? 0), 0), $lineSubtotal);
                $lineTotal = round(max($lineSubtotal - $discountAmount, 0), 2);

                DB::table('sales_order_lines')
                    ->where('id', $row->id)
                    ->update([
                        'unit_price' => $materialUnit,
                        'labor_unit' => $laborUnit,
                        'material_total' => $materialTotal,
                        'labor_total' => $laborTotal,
                        'line_subtotal' => $lineSubtotal,
                        'line_total' => $lineTotal,
                    ]);
            }
        }
    }

    public function down(): void
    {
        // no-op data repair migration
    }
};

