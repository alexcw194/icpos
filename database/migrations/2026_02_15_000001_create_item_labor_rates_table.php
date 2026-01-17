<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('item_labor_rates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('item_id')
                ->unique()
                ->constrained('items')
                ->cascadeOnDelete();
            $t->decimal('labor_unit_cost', 18, 2)->default(0);
            $t->string('notes', 255)->nullable();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_labor_rates');
    }
};
