<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bq_line_template_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('bq_line_template_id')
                ->constrained('bq_line_templates')
                ->cascadeOnDelete();
            $t->integer('sort_order')->default(0);
            $t->enum('type', ['charge', 'percent']);
            $t->string('label');
            $t->decimal('default_qty', 12, 2)->nullable()->default(1.00);
            $t->string('default_unit', 20)->nullable()->default('LS');
            $t->decimal('default_unit_price', 18, 2)->nullable();
            $t->decimal('percent_value', 9, 4)->nullable();
            $t->enum('basis_type', ['bq_product_total', 'section_product_total'])->default('bq_product_total');
            $t->enum('applies_to', ['material', 'labor', 'both'])->default('both');
            $t->boolean('editable_price')->default(true);
            $t->boolean('editable_percent')->default(true);
            $t->boolean('can_remove')->default(true);
            $t->timestamps();

            $t->index(['bq_line_template_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bq_line_template_lines');
    }
};
