<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('billing_document_lines')) {
            return;
        }

        Schema::table('billing_document_lines', function (Blueprint $table) {
            if (!Schema::hasColumn('billing_document_lines', 'labor_unit')) {
                $table->decimal('labor_unit', 18, 2)->default(0)->after('unit_price');
            }
            if (!Schema::hasColumn('billing_document_lines', 'material_total')) {
                $table->decimal('material_total', 18, 2)->default(0)->after('labor_unit');
            }
            if (!Schema::hasColumn('billing_document_lines', 'labor_total')) {
                $table->decimal('labor_total', 18, 2)->default(0)->after('material_total');
            }
        });

        if (
            !Schema::hasTable('billing_documents')
            || !Schema::hasTable('sales_orders')
            || !Schema::hasColumn('billing_document_lines', 'billing_document_id')
            || !Schema::hasColumn('billing_document_lines', 'sales_order_line_id')
            || !Schema::hasColumn('billing_document_lines', 'qty')
            || !Schema::hasColumn('billing_document_lines', 'line_subtotal')
            || !Schema::hasColumn('sales_orders', 'po_type')
            || !Schema::hasColumn('billing_documents', 'billing_component')
            || !Schema::hasColumn('billing_documents', 'sales_order_id')
        ) {
            return;
        }

        $hasSalesOrderLineTable = Schema::hasTable('sales_order_lines')
            && Schema::hasColumn('sales_order_lines', 'id')
            && Schema::hasColumn('sales_order_lines', 'unit_price')
            && Schema::hasColumn('sales_order_lines', 'labor_unit')
            && Schema::hasColumn('sales_order_lines', 'material_total')
            && Schema::hasColumn('sales_order_lines', 'labor_total');

        $lastId = 0;
        $chunk = 500;

        while (true) {
            $query = DB::table('billing_document_lines as bdl')
                ->join('billing_documents as bd', 'bd.id', '=', 'bdl.billing_document_id')
                ->leftJoin('sales_orders as so', 'so.id', '=', 'bd.sales_order_id')
                ->where('bdl.id', '>', $lastId)
                ->orderBy('bdl.id')
                ->limit($chunk)
                ->select([
                    'bdl.id',
                    'bdl.qty',
                    'bdl.unit_price',
                    'bdl.line_subtotal',
                    'bdl.sales_order_line_id',
                    'bd.billing_component',
                    'so.po_type',
                ]);

            if ($hasSalesOrderLineTable) {
                $query->leftJoin('sales_order_lines as sol', 'sol.id', '=', 'bdl.sales_order_line_id')
                    ->addSelect([
                        'sol.unit_price as so_unit_price',
                        'sol.labor_unit as so_labor_unit',
                        'sol.material_total as so_material_total',
                        'sol.labor_total as so_labor_total',
                    ]);
            }

            $rows = $query->get();
            if ($rows->isEmpty()) {
                break;
            }

            foreach ($rows as $row) {
                $lastId = (int) $row->id;
                $poType = strtolower((string) ($row->po_type ?? ''));
                if ($poType !== 'project') {
                    continue;
                }

                $qty = max((float) ($row->qty ?? 0), 0);
                $lineSubtotal = max((float) ($row->line_subtotal ?? 0), 0);
                $materialUnit = max((float) ($row->unit_price ?? 0), 0);
                $laborUnit = 0.0;
                $materialTotal = 0.0;
                $laborTotal = 0.0;

                if ($hasSalesOrderLineTable && !empty($row->sales_order_line_id)) {
                    $materialUnit = max((float) ($row->so_unit_price ?? $materialUnit), 0);
                    $laborUnit = max((float) ($row->so_labor_unit ?? 0), 0);
                    if ($qty > 0) {
                        $materialTotal = round($qty * $materialUnit, 2);
                        $laborTotal = round($qty * $laborUnit, 2);

                        if (abs(($materialTotal + $laborTotal) - $lineSubtotal) > 0.01 && $lineSubtotal > 0) {
                            $sourceMaterial = max((float) ($row->so_material_total ?? 0), 0);
                            $sourceLabor = max((float) ($row->so_labor_total ?? 0), 0);
                            $sourceTotal = $sourceMaterial + $sourceLabor;
                            if ($sourceTotal > 0) {
                                $materialTotal = round($lineSubtotal * ($sourceMaterial / $sourceTotal), 2);
                                $laborTotal = round($lineSubtotal - $materialTotal, 2);
                                $materialUnit = round($materialTotal / $qty, 2);
                                $laborUnit = round($laborTotal / $qty, 2);
                            }
                        }
                    }
                }

                if ($materialTotal <= 0 && $laborTotal <= 0) {
                    $component = strtolower(trim((string) ($row->billing_component ?? 'combined')));
                    if ($component === 'labor') {
                        $laborTotal = round($lineSubtotal, 2);
                        $materialTotal = 0.0;
                        $laborUnit = $qty > 0 ? round($laborTotal / $qty, 2) : 0.0;
                    } else {
                        $materialTotal = round($lineSubtotal, 2);
                        $laborTotal = 0.0;
                        $materialUnit = $qty > 0 ? round($materialTotal / $qty, 2) : $materialUnit;
                    }
                }

                DB::table('billing_document_lines')
                    ->where('id', (int) $row->id)
                    ->update([
                        'labor_unit' => round(max($laborUnit, 0), 2),
                        'material_total' => round(max($materialTotal, 0), 2),
                        'labor_total' => round(max($laborTotal, 0), 2),
                    ]);
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('billing_document_lines')) {
            return;
        }

        Schema::table('billing_document_lines', function (Blueprint $table) {
            if (Schema::hasColumn('billing_document_lines', 'labor_total')) {
                $table->dropColumn('labor_total');
            }
            if (Schema::hasColumn('billing_document_lines', 'material_total')) {
                $table->dropColumn('material_total');
            }
            if (Schema::hasColumn('billing_document_lines', 'labor_unit')) {
                $table->dropColumn('labor_unit');
            }
        });
    }
};

