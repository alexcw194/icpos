<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('delivery_lines', function (Blueprint $table) {
            // Tambah kolom setelah quotation_line_id (posisi opsional)
            if (!Schema::hasColumn('delivery_lines', 'sales_order_line_id')) {
                $table->foreignId('sales_order_line_id')
                      ->nullable()
                      ->after('quotation_line_id')
                      ->constrained('sales_order_lines')
                      ->nullOnDelete(); // kalau SO line dihapus → set null
            }

            // Opsional: index untuk pencarian cepat
            $table->index(['delivery_id', 'sales_order_line_id'], 'dl_delivery_so_idx');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_lines', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_lines', 'sales_order_line_id')) {
                // drop FK + kolom
                $table->dropConstrainedForeignId('sales_order_line_id');
            }
            // drop index opsional
            if (Schema::hasColumn('delivery_lines', 'delivery_id')) {
                $table->dropIndex('dl_delivery_so_idx');
            }
        });
    }
};
