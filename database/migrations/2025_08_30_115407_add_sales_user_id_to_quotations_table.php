<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            // Tambahkan kolom hanya jika belum ada
            if (!Schema::hasColumn('quotations', 'sales_user_id')) {
                $table->foreignId('sales_user_id')
                      ->nullable()
                      ->after('customer_id')
                      ->constrained('users')
                      ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            if (Schema::hasColumn('quotations', 'sales_user_id')) {
                // Coba lepas FK (abaikan error kalau FK tidak ada)
                try { $table->dropForeign(['sales_user_id']); } catch (\Throwable $e) {}
                // Hapus kolom
                $table->dropColumn('sales_user_id');
            }
        });
    }
};
