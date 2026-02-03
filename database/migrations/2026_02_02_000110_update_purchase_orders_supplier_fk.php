<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('purchase_orders')) {
            return;
        }

        $schema = DB::getDatabaseName();
        $constraints = DB::select(
            'SELECT constraint_name, referenced_table_name FROM information_schema.key_column_usage
             WHERE table_schema = ? AND table_name = ? AND column_name = ? AND referenced_table_name IS NOT NULL',
            [$schema, 'purchase_orders', 'supplier_id']
        );

        $hasSupplierFk = false;
        foreach ($constraints as $row) {
            if ($row->referenced_table_name === 'suppliers' && $row->constraint_name === 'purchase_orders_supplier_id_foreign') {
                $hasSupplierFk = true;
                continue;
            }
            DB::statement("ALTER TABLE `purchase_orders` DROP FOREIGN KEY `{$row->constraint_name}`");
        }

        if (!$hasSupplierFk) {
            Schema::table('purchase_orders', function (Blueprint $t) {
                $t->foreign('supplier_id')
                    ->references('id')
                    ->on('suppliers')
                    ->cascadeOnUpdate();
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('purchase_orders')) {
            return;
        }

        $schema = DB::getDatabaseName();
        $constraints = DB::select(
            'SELECT constraint_name, referenced_table_name FROM information_schema.key_column_usage
             WHERE table_schema = ? AND table_name = ? AND column_name = ? AND referenced_table_name IS NOT NULL',
            [$schema, 'purchase_orders', 'supplier_id']
        );

        $hasCustomerFk = false;
        foreach ($constraints as $row) {
            if ($row->referenced_table_name === 'customers' && $row->constraint_name === 'purchase_orders_supplier_id_foreign') {
                $hasCustomerFk = true;
                continue;
            }
            DB::statement("ALTER TABLE `purchase_orders` DROP FOREIGN KEY `{$row->constraint_name}`");
        }

        if (!$hasCustomerFk) {
            Schema::table('purchase_orders', function (Blueprint $t) {
                $t->foreign('supplier_id')
                    ->references('id')
                    ->on('customers')
                    ->cascadeOnUpdate();
            });
        }
    }
};
