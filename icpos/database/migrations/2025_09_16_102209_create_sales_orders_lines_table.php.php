<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sales_order_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $t->integer('position')->default(0);

            $t->string('name');
            $t->text('description')->nullable();
            $t->string('unit', 20)->nullable();

            $t->decimal('qty_ordered', 18, 2)->default(0);
            $t->decimal('unit_price', 18, 2)->default(0);

            $t->enum('discount_type', ['amount','percent'])->default('amount');
            $t->decimal('discount_value', 18, 2)->default(0);
            $t->decimal('discount_amount', 18, 2)->default(0);

            $t->decimal('line_subtotal', 18, 2)->default(0); // qty*price
            $t->decimal('line_total', 18, 2)->default(0);    // subtotal - discount_amount

            $t->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('sales_order_lines');
    }
};
