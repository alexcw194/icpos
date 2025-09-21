<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales_order_lines', function (Blueprint $table) {
            // Boleh null agar dokumen lama tetap aman, dan baris manual tetap bisa
            $table->foreignId('item_id')->nullable()->after('description')
                  ->constrained('items')->nullOnDelete();
            $table->foreignId('item_variant_id')->nullable()->after('item_id')
                  ->constrained('item_variants')->nullOnDelete();

            $table->index(['item_id', 'item_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::table('sales_order_lines', function (Blueprint $table) {
            // drop FK dulu baru kolom (urutan penting untuk MySQL)
            $table->dropConstrainedForeignId('item_variant_id');
            $table->dropConstrainedForeignId('item_id');
            $table->dropIndex(['sales_order_lines_item_id_item_variant_id_index']);
        });
    }
};
