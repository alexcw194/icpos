<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('project_quotation_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('section_id')
                ->constrained('project_quotation_sections')
                ->cascadeOnDelete();
            $t->string('line_no', 32)->nullable();
            $t->text('description');
            $t->decimal('qty', 18, 2)->default(1);
            $t->string('unit', 16)->default('PCS');
            $t->decimal('unit_price', 18, 2)->default(0);
            $t->decimal('material_total', 18, 2)->default(0);
            $t->decimal('labor_total', 18, 2)->default(0);
            $t->decimal('line_total', 18, 2)->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_quotation_lines');
    }
};
