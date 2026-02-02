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

        // Drop existing FK (customers) if present
        $schema = DB::getDatabaseName();
        $row = DB::selectOne(
            'SELECT constraint_name FROM information_schema.key_column_usage WHERE table_schema = ? AND table_name = ? AND column_name = ? AND referenced_table_name IS NOT NULL LIMIT 1',
            [$schema, 'purchase_orders', 'supplier_id']
        );
        if ($row && !empty($row->constraint_name)) {
            DB::statement("ALTER TABLE `purchase_orders` DROP FOREIGN KEY `{$row->constraint_name}`");
        }

        // Re-add FK to suppliers
        Schema::table('purchase_orders', function (Blueprint $t) {
            $t->foreign('supplier_id')->references('id')->on('suppliers')->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('purchase_orders')) {
            return;
        }

        $schema = DB::getDatabaseName();
        $row = DB::selectOne(
            'SELECT constraint_name FROM information_schema.key_column_usage WHERE table_schema = ? AND table_name = ? AND column_name = ? AND referenced_table_name IS NOT NULL LIMIT 1',
            [$schema, 'purchase_orders', 'supplier_id']
        );
        if ($row && !empty($row->constraint_name)) {
            DB::statement("ALTER TABLE `purchase_orders` DROP FOREIGN KEY `{$row->constraint_name}`");
        }

        Schema::table('purchase_orders', function (Blueprint $t) {
            $t->foreign('supplier_id')->references('id')->on('customers')->cascadeOnUpdate();
        });
    }
};
