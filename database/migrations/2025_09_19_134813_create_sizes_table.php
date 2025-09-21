<?php

// database/migrations/2025_09_19_000001_create_sizes_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sizes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();   // contoh: S, M, L, 20M, 30M, 1/2", dst
            $table->string('slug')->unique();   // s, m, l, 20m, 30m (optional utk URL/teknis)
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('sizes');
    }
};
