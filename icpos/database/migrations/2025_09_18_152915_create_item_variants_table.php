<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('item_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();

            // SKU unik per varian; boleh null (MySQL: banyak NULL tetap allowed untuk unique)
            $table->string('sku')->nullable()->unique();

            $table->decimal('price', 15, 2)->default(0);
            $table->unsignedInteger('stock')->default(0);

            // { "color": "Blue", "size": "M", "length": 20 }
            $table->json('attributes')->nullable();

            $table->boolean('is_active')->default(true);
            $table->string('barcode', 64)->nullable();
            $table->unsignedInteger('min_stock')->default(0);

            $table->timestamps();

            $table->index(['item_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_variants');
    }
};
