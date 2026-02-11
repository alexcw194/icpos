<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('purchase_price_histories')) {
            return;
        }

        Schema::create('purchase_price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('item_variant_id')->nullable()->constrained('item_variants')->nullOnDelete();
            $table->decimal('price', 18, 2);
            $table->date('effective_date');
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('purchase_order_line_id')->nullable()->constrained('purchase_order_lines')->nullOnDelete();
            $table->foreignId('source_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->timestamps();

            $table->index(['item_id', 'item_variant_id', 'effective_date', 'id'], 'pph_lookup_idx');
            $table->unique('purchase_order_line_id', 'pph_po_line_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_price_histories');
    }
};
