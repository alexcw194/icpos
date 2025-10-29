<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Stabilkan row format sebelum add UNIQUE di utf8mb4
        DB::statement('ALTER TABLE goods_receipts ROW_FORMAT=DYNAMIC');

        Schema::table('goods_receipts', function (Blueprint $t) {
            // UNIQUE setelah table stabil
            $t->unique('number', 'goods_receipts_number_unique');

            // Foreign Keys (error akan spesifik jika mismatch)
            $t->foreign('company_id')
                ->references('id')->on('companies')->cascadeOnUpdate();

            $t->foreign('warehouse_id')
                ->references('id')->on('warehouses')->nullOnDelete();

            $t->foreign('purchase_order_id')
                ->references('id')->on('purchase_orders')->nullOnDelete();

            $t->foreign('posted_by')
                ->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('goods_receipt_lines', function (Blueprint $t) {
            $t->foreign('goods_receipt_id')
                ->references('id')->on('goods_receipts')->cascadeOnDelete();

            $t->foreign('item_id')
                ->references('id')->on('items')->cascadeOnUpdate();

            $t->foreign('item_variant_id')
                ->references('id')->on('item_variants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipt_lines', function (Blueprint $t) {
            $t->dropForeign(['goods_receipt_id']);
            $t->dropForeign(['item_id']);
            $t->dropForeign(['item_variant_id']);
        });

        Schema::table('goods_receipts', function (Blueprint $t) {
            $t->dropForeign(['company_id']);
            $t->dropForeign(['warehouse_id']);
            $t->dropForeign(['purchase_order_id']);
            $t->dropForeign(['posted_by']);
            $t->dropUnique('goods_receipts_number_unique');
        });
    }
};
