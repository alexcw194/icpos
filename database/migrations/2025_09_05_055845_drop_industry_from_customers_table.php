<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Aman di-run berulang: cek dulu baru drop
        if (Schema::hasColumn('customers', 'industry')) {
            Schema::table('customers', function (Blueprint $table) {
                // Kalau dulu pernah ada index/unique di 'industry', drop di sini dulu.
                // Contoh (uncomment jika memang ada):
                // $table->dropIndex(['industry']);       // untuk index biasa
                // $table->dropUnique(['industry']);      // untuk unique
                $table->dropColumn('industry');
            });
        }
    }

    public function down(): void
    {
        // Rollback: tambahkan kembali kolom sebagai nullable (legacy-safe)
        if (!Schema::hasColumn('customers', 'industry')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('industry')->nullable()->after('name');
                // Kalau dulu ada index/unique, kembalikan di sini:
                // $table->index('industry');
                // $table->unique('industry');
            });
        }
    }
};
