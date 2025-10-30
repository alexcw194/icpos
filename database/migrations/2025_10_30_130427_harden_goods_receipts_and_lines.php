<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Stabilize row format to avoid key-length surprises on utf8mb4
        try { DB::statement('ALTER TABLE goods_receipts ROW_FORMAT=DYNAMIC'); } catch (\Throwable $e) {}

        Schema::table('goods_receipts', function (Blueprint $t) {
            // UNIQUE number
            if (! $this->hasIndex('goods_receipts', 'goods_receipts_number_unique')) {
                $t->unique('number', 'goods_receipts_number_unique');
            }
            // FKs
            if (! $this->hasForeign('goods_receipts', 'goods_receipts_company_id_foreign')) {
                $t->foreign('company_id')->references('id')->on('companies')->cascadeOnUpdate();
            }
            if (Schema::hasColumn('goods_receipts','warehouse_id') && ! $this->hasForeign('goods_receipts','goods_receipts_warehouse_id_foreign')) {
                $t->foreign('warehouse_id')->references('id')->on('warehouses')->nullOnDelete();
            }
            if (Schema::hasColumn('goods_receipts','purchase_order_id') && ! $this->hasForeign('goods_receipts','goods_receipts_purchase_order_id_foreign')) {
                $t->foreign('purchase_order_id')->references('id')->on('purchase_orders')->nullOnDelete();
            }
            if (Schema::hasColumn('goods_receipts','posted_by') && ! $this->hasForeign('goods_receipts','goods_receipts_posted_by_foreign')) {
                $t->foreign('posted_by')->references('id')->on('users')->nullOnDelete();
            }
        });

        Schema::table('goods_receipt_lines', function (Blueprint $t) {
            if (! $this->hasForeign('goods_receipt_lines','grl_gr_id_foreign')) {
                $t->foreign('goods_receipt_id', 'grl_gr_id_foreign')
                  ->references('id')->on('goods_receipts')->cascadeOnDelete();
            }
            if (! $this->hasForeign('goods_receipt_lines','grl_item_id_foreign')) {
                $t->foreign('item_id', 'grl_item_id_foreign')
                  ->references('id')->on('items')->cascadeOnUpdate();
            }
            if (Schema::hasColumn('goods_receipt_lines','item_variant_id') && ! $this->hasForeign('goods_receipt_lines','grl_variant_id_foreign')) {
                $t->foreign('item_variant_id', 'grl_variant_id_foreign')
                  ->references('id')->on('item_variants')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipt_lines', function (Blueprint $t) {
            $this->dropForeignIfExists($t, 'grl_gr_id_foreign');
            $this->dropForeignIfExists($t, 'grl_item_id_foreign');
            $this->dropForeignIfExists($t, 'grl_variant_id_foreign');
        });

        Schema::table('goods_receipts', function (Blueprint $t) {
            $this->dropForeignIfExists($t, 'goods_receipts_company_id_foreign');
            $this->dropForeignIfExists($t, 'goods_receipts_warehouse_id_foreign');
            $this->dropForeignIfExists($t, 'goods_receipts_purchase_order_id_foreign');
            $this->dropForeignIfExists($t, 'goods_receipts_posted_by_foreign');

            if ($this->hasIndex('goods_receipts','goods_receipts_number_unique')) {
                $t->dropUnique('goods_receipts_number_unique');
            }
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        return collect(DB::select("SHOW INDEX FROM {$table}"))->pluck('Key_name')->contains($index);
    }
    private function hasForeign(string $table, string $fk): bool
    {
        $db = DB::getDatabaseName();
        $cnt = DB::selectOne("
            SELECT COUNT(*) AS c FROM information_schema.key_column_usage
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?
        ", [$db, $table, $fk]);
        return ($cnt->c ?? 0) > 0;
    }
    private function dropForeignIfExists(Blueprint $t, string $fk): void
    {
        try { $t->dropForeign($fk); } catch (\Throwable $e) {}
    }
};
