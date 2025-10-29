<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('purchase_order_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('item_id')->constrained()->cascadeOnUpdate();
            $t->foreignId('item_variant_id')->nullable()->constrained()->nullOnDelete();
            $t->string('item_name_snapshot'); // freeze name/sku display
            $t->string('sku_snapshot')->nullable();
            $t->decimal('qty_ordered', 18, 4);
            $t->decimal('qty_received', 18, 4)->default(0);
            $t->string('uom', 16)->nullable();
            $t->decimal('unit_price', 18, 2)->default(0);
            $t->decimal('line_total', 18, 2)->default(0);
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('purchase_order_lines'); }
};
