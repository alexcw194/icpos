<?php

// database/migrations/2025_09_19_000002_create_colors_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('colors', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();   // contoh: Red, Navy, Black
            $table->string('slug')->unique();   // red, navy, black
            $table->string('hex', 7)->nullable(); // #RRGGBB (opsional, untuk badge)
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('colors');
    }
};
