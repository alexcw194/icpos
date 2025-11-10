<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('stock_adjustments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('item_id')->constrained()->cascadeOnDelete();
            $t->foreignId('variant_id')->nullable()->constrained('item_variants')->nullOnDelete();
            $t->decimal('qty_adjustment', 18, 4);
            $t->text('reason')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('stock_adjustments');
    }
};
