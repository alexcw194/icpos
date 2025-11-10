<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manufacture_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('component_item_id')->constrained('items')->cascadeOnDelete();
            $table->decimal('qty_required', 12, 3);
            $table->decimal('unit_factor', 12, 3)->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['parent_item_id', 'component_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manufacture_recipes');
    }
};
