<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('quotation_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $t->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->text('description')->nullable();
            $t->decimal('qty', 15, 2)->default(1);
            $t->string('unit', 16)->default('pcs');
            $t->decimal('unit_price', 15, 2)->default(0);
            $t->decimal('line_total', 15, 2)->default(0);
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('quotation_items');
    }
};
