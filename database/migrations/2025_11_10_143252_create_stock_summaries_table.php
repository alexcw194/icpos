<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('stock_summaries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $t->foreignId('warehouse_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $t->foreignId('item_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $t->foreignId('variant_id')->nullable()->constrained('item_variants')->cascadeOnUpdate()->nullOnDelete();

            $t->decimal('qty_balance', 18, 4)->default(0);
            $t->string('uom', 32)->nullable();

            $t->timestamps();

            $t->unique(['company_id','warehouse_id','item_id','variant_id'], 'ux_stock_summaries_scope');
            $t->index(['warehouse_id','item_id','variant_id'], 'ix_stock_summaries_lookup');
        });
    }
    public function down(): void {
        Schema::dropIfExists('stock_summaries');
    }
};
