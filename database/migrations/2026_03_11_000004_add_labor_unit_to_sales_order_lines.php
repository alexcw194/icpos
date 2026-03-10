<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sales_order_lines')) {
            return;
        }

        Schema::table('sales_order_lines', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_order_lines', 'labor_unit')) {
                $table->decimal('labor_unit', 18, 2)
                    ->default(0)
                    ->after('unit_price');
            }
        });

        if (!Schema::hasTable('sales_orders') || !Schema::hasColumn('sales_orders', 'po_type')) {
            return;
        }

        $lastId = 0;
        $chunkSize = 500;

        while (true) {
            $rows = DB::table('sales_order_lines as sol')
                ->join('sales_orders as so', 'so.id', '=', 'sol.sales_order_id')
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
                    'sol.discount_amount',
                ]);

            if ($rows->isEmpty()) {
                break;
            }

            foreach ($rows as $row) {
                $lastId = (int) $row->id;

                $qty = max((float) ($row->qty_ordered ?? 0), 0);
                $materialUnit = max((float) ($row->unit_price ?? 0), 0);
                $laborUnit = max((float) ($row->labor_unit ?? 0), 0);
                $materialTotal = max((float) ($row->material_total ?? 0), 0);
                $laborTotal = max((float) ($row->labor_total ?? 0), 0);

                if ($qty > 0) {
                    if ($materialUnit <= 0 && $materialTotal > 0) {
                        $materialUnit = round($materialTotal / $qty, 2);
                    }
                    if ($laborUnit <= 0 && $laborTotal > 0) {
                        $laborUnit = round($laborTotal / $qty, 2);
                    }

                    $materialTotal = round($qty * $materialUnit, 2);
                    $laborTotal = round($qty * $laborUnit, 2);
                } else {
                    $laborUnit = 0.0;
                    $materialTotal = 0.0;
                    $laborTotal = 0.0;
                }

                $lineSubtotal = round($materialTotal + $laborTotal, 2);
                $discountAmount = min(max((float) ($row->discount_amount ?? 0), 0), $lineSubtotal);
                $lineTotal = round(max($lineSubtotal - $discountAmount, 0), 2);

                DB::table('sales_order_lines')
                    ->where('id', $row->id)
                    ->update([
                        'unit_price' => round($materialUnit, 2),
                        'labor_unit' => round($laborUnit, 2),
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
        if (!Schema::hasTable('sales_order_lines')) {
            return;
        }

        Schema::table('sales_order_lines', function (Blueprint $table) {
            if (Schema::hasColumn('sales_order_lines', 'labor_unit')) {
                $table->dropColumn('labor_unit');
            }
        });
    }
};

