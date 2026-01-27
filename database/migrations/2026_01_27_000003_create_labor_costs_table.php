<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('labor_costs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('labor_id')
                ->constrained('labors')
                ->cascadeOnDelete();
            $t->foreignId('sub_contractor_id')
                ->constrained('sub_contractors')
                ->cascadeOnDelete();
            $t->decimal('cost_amount', 18, 2)->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['labor_id', 'sub_contractor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('labor_costs');
    }
};
