<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bq_line_catalogs', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->enum('type', ['charge', 'percent']);
            $t->decimal('default_qty', 12, 2)->nullable()->default(1.00);
            $t->string('default_unit', 20)->nullable()->default('LS');
            $t->decimal('default_unit_price', 18, 2)->nullable();
            $t->decimal('default_percent', 9, 4)->nullable();
            $t->enum('percent_basis', ['product_subtotal', 'section_product_subtotal'])
                ->default('product_subtotal');
            $t->enum('cost_bucket', ['material', 'labor', 'overhead', 'other'])->default('overhead');
            $t->boolean('is_active')->default(true);
            $t->text('description')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bq_line_catalogs');
    }
};
