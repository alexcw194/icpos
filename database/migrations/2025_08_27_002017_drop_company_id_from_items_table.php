<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Lepas FK & index lama kalau ada
        try {
            Schema::table('items', function (Blueprint $t) {
                $t->dropForeign(['company_id']); // default: items_company_id_foreign
            });
        } catch (\Throwable $e) {}

        try {
            Schema::table('items', function (Blueprint $t) {
                $t->dropUnique('items_company_id_sku_unique'); // kalau dulu sempat buat unique (company_id, sku)
            });
        } catch (\Throwable $e) {}

        // Pastikan SKU unik global (aman jika sudah ada)
        try {
            Schema::table('items', function (Blueprint $t) {
                $t->unique('sku', 'items_sku_unique');
            });
        } catch (\Throwable $e) {}

        // Hapus kolom company_id
        Schema::table('items', function (Blueprint $t) {
            if (Schema::hasColumn('items', 'company_id')) {
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
        // (opsional) kembalikan unique komposit lama jika perlu:
        // Schema::table('items', function (Blueprint $t) {
        //     $t->dropUnique('items_sku_unique');
        //     $t->unique(['company_id','sku'], 'items_company_id_sku_unique');
        // });
    }
};
