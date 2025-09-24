<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('item_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_variant_id')->nullable()->constrained('item_variants')->nullOnDelete();
            $table->decimal('qty_on_hand', 18, 4)->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'warehouse_id', 'item_id', 'item_variant_id'], 'item_stocks_company_item_variant');
        });

        Schema::create('stock_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_variant_id')->nullable()->constrained('item_variants')->nullOnDelete();
            $table->dateTime('ledger_date');
            $table->decimal('qty_change', 18, 4);
            $table->decimal('balance_after', 18, 4)->nullable();
            $table->string('reference_type', 40);
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['reference_type', 'reference_id'], 'stock_ledgers_reference_idx');
            $table->index(['warehouse_id', 'item_id', 'item_variant_id', 'ledger_date'], 'stock_ledgers_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_ledgers');
        Schema::dropIfExists('item_stocks');
    }
};
