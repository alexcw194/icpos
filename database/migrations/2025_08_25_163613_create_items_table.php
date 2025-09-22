<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1) Pastikan tabel items ada dulu
        if (!Schema::hasTable('items')) {
            Schema::create('items', function (Blueprint $t) {
                $t->id();                 // pk
                $t->string('name', 191)->nullable();   // minimal kolom (boleh kosong)
                $t->string('sku', 191)->nullable();    // disiapkan kalau nanti mau unique
                $t->timestamps();
            });
        }

        // 2) Operasi "legacy cleanup" (aman jika belum ada)
        try {
            Schema::table('items', function (Blueprint $t) {
                $t->dropForeign(['company_id']); // items_company_id_foreign
            });
        } catch (\Throwable $e) {}

        try {
            Schema::table('items', function (Blueprint $t) {
                $t->dropUnique('items_company_id_sku_unique');
            });
        } catch (\Throwable $e) {}

        // Tambah unique(sku) hanya kalau kolom sku memang ada
        if (Schema::hasColumn('items', 'sku')) {
            try {
                Schema::table('items', function (Blueprint $t) {
                    $t->unique('sku', 'items_sku_unique');
                });
            } catch (\Throwable $e) {}
        }

        // Hapus kolom company_id jika ada
        if (Schema::hasColumn('items','company_id')) {
            Schema::table('items', function (Blueprint $t) {
                $t->dropColumn('company_id');
            });
        }
    }

    public function down(): void
    {
        // Versi sederhana: drop tabel
        Schema::dropIfExists('items');
        // (kalau mau persis seperti sebelumnya, kamu bisa kembalikan logic restore company_id di sini)
    }
};