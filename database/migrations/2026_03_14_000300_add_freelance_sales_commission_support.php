<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_order_lines')) {
            Schema::table('sales_order_lines', function (Blueprint $table) {
                if (!Schema::hasColumn('sales_order_lines', 'commission_basis_unit_price')) {
                    $table->decimal('commission_basis_unit_price', 18, 2)
                        ->nullable()
                        ->after('item_variant_id');
                }
            });

            if (
                Schema::hasColumn('sales_order_lines', 'commission_basis_unit_price')
                && Schema::hasColumn('sales_order_lines', 'item_id')
                && Schema::hasColumn('sales_order_lines', 'unit_price')
                && Schema::hasTable('items')
                && Schema::hasColumn('items', 'id')
                && Schema::hasColumn('items', 'price')
            ) {
                DB::table('sales_order_lines')
                    ->whereNull('commission_basis_unit_price')
                    ->select('id', 'item_id', 'unit_price')
                    ->orderBy('id')
                    ->chunkById(200, function ($rows) {
                        $itemPrices = DB::table('items')
                            ->whereIn('id', collect($rows)->pluck('item_id')->filter()->unique()->all())
                            ->pluck('price', 'id');

                        foreach ($rows as $row) {
                            $basis = $itemPrices->get($row->item_id) !== null
                                ? (float) $itemPrices->get($row->item_id)
                                : (float) ($row->unit_price ?? 0);
                            DB::table('sales_order_lines')
                                ->where('id', $row->id)
                                ->update(['commission_basis_unit_price' => round(max($basis, 0), 2)]);
                        }
                    }, 'id');
            }
        }

        if (Schema::hasTable('sales_commission_note_lines')) {
            Schema::table('sales_commission_note_lines', function (Blueprint $table) {
                if (!Schema::hasColumn('sales_commission_note_lines', 'commission_mode')) {
                    $table->string('commission_mode', 32)->default('percentage')->after('project_scope');
                }
                if (!Schema::hasColumn('sales_commission_note_lines', 'basis_unit_price_snapshot')) {
                    $table->decimal('basis_unit_price_snapshot', 18, 2)->nullable()->after('rate_percent');
                }
                if (!Schema::hasColumn('sales_commission_note_lines', 'basis_net_amount')) {
                    $table->decimal('basis_net_amount', 18, 2)->nullable()->after('basis_unit_price_snapshot');
                }
                if (!Schema::hasColumn('sales_commission_note_lines', 'actual_net_amount')) {
                    $table->decimal('actual_net_amount', 18, 2)->nullable()->after('basis_net_amount');
                }
                if (!Schema::hasColumn('sales_commission_note_lines', 'formula_label_snapshot')) {
                    $table->string('formula_label_snapshot', 191)->nullable()->after('actual_net_amount');
                }
            });
        }

        if (Schema::hasTable('settings')) {
            DB::table('settings')->updateOrInsert(
                ['key' => 'sales.commission.freelance.icpos_discount_percent'],
                ['value' => '35', 'updated_at' => now(), 'created_at' => now()]
            );
        }

        if (Schema::hasTable('roles')) {
            DB::table('roles')->updateOrInsert(
                ['name' => 'Freelance', 'guard_name' => 'web'],
                ['updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sales_commission_note_lines')) {
            Schema::table('sales_commission_note_lines', function (Blueprint $table) {
                foreach ([
                    'formula_label_snapshot',
                    'actual_net_amount',
                    'basis_net_amount',
                    'basis_unit_price_snapshot',
                    'commission_mode',
                ] as $column) {
                    if (Schema::hasColumn('sales_commission_note_lines', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('sales_order_lines')) {
            Schema::table('sales_order_lines', function (Blueprint $table) {
                if (Schema::hasColumn('sales_order_lines', 'commission_basis_unit_price')) {
                    $table->dropColumn('commission_basis_unit_price');
                }
            });
        }
    }
};
