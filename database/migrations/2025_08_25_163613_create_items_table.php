<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Lepas FK (abaikan jika belum ada)
        try {
            Schema::table('items', function (Blueprint $t) {
                $t->dropForeign(['company_id']); // nama default: items_company_id_foreign
            });
        } catch (\Throwable $e) {}

        // Hapus index komposit lama kalau pernah dibuat (aman kalau tidak ada)
        try {
            Schema::table('items', function (Blueprint $t) {
                $t->dropUnique('items_company_id_sku_unique');
            });
        } catch (\Throwable $e) {}

        // Pastikan SKU unik secara global (opsional kalau sudah ada)
        try {
            Schema::table('items', function (Blueprint $t) {
                $t->unique('sku', 'items_sku_unique');
            });
        } catch (\Throwable $e) {}

        // Hapus kolom company_id
        Schema::table('items', function (Blueprint $t) {
            if (Schema::hasColumn('items','company_id')) {
                $t->dropColumn('company_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $t) {
            $t->foreignId('company_id')->nullable()->after('id');
        });
        try {
            Schema::table('items', function (Blueprint $t) {
                $t->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
            });
        } catch (\Throwable $e) {}
        // Jika perlu kembalikan unique komposit:
        // Schema::table('items', function (Blueprint $t) {
        //     $t->dropUnique('items_sku_unique');
        //     $t->unique(['company_id','sku'], 'items_company_id_sku_unique');
        // });
    }
};
