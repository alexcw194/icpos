<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ld_grid_cells', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->decimal('center_lat', 10, 7);
            $table->decimal('center_lng', 10, 7);
            $table->unsignedInteger('radius_m');
            $table->string('region_code', 32)->nullable();
            $table->string('city', 100)->nullable()->index();
            $table->string('province', 100)->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_scanned_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ld_grid_cells');
    }
};

