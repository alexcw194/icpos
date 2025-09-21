<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            // Sales pelaksana quotation, boleh null. Jika user dihapus → set null.
            if (!Schema::hasColumn('quotations', 'sales_user_id')) {
                $table->foreignId('sales_user_id')
                      ->nullable()
                      ->constrained('users')
                      ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            if (Schema::hasColumn('quotations', 'sales_user_id')) {
                // Laravel 8.76+: dropConstrainedForeignId akan drop FK + kolom
                if (method_exists($table, 'dropConstrainedForeignId')) {
                    $table->dropConstrainedForeignId('sales_user_id');
                } else {
                    // Fallback jika versi lama
                    $table->dropForeign('quotations_sales_user_id_foreign');
                    $table->dropColumn('sales_user_id');
                }
            }
        });
    }
};
