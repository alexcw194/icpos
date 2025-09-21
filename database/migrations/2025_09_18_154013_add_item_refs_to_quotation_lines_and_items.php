<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // quotation_lines
        Schema::table('quotation_lines', function (Blueprint $table) {
            $table->foreignId('item_id')->nullable()->after('description')
                  ->constrained('items')->nullOnDelete();
            $table->foreignId('item_variant_id')->nullable()->after('item_id')
                  ->constrained('item_variants')->nullOnDelete();
            $table->index(['item_id','item_variant_id']);
        });

        // quotation_items (kalau tabel ini memang dipakai)
        if (Schema::hasTable('quotation_items')) {
            Schema::table('quotation_items', function (Blueprint $table) {
                if (!Schema::hasColumn('quotation_items','item_variant_id')) {
                    $table->foreignId('item_variant_id')->nullable()->after('item_id')
                          ->constrained('item_variants')->nullOnDelete();
                    $table->index('item_variant_id');
                }
            });
        }
    }

    public function down(): void
    {
        // quotation_lines
        Schema::table('quotation_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('item_variant_id');
            $table->dropConstrainedForeignId('item_id');
            $table->dropIndex(['quotation_lines_item_id_item_variant_id_index']);
        });

        // quotation_items
        if (Schema::hasTable('quotation_items')) {
            Schema::table('quotation_items', function (Blueprint $table) {
                if (Schema::hasColumn('quotation_items','item_variant_id')) {
                    $table->dropConstrainedForeignId('item_variant_id');
                    $table->dropIndex(['quotation_items_item_variant_id_index']);
                }
            });
        }
    }
};
