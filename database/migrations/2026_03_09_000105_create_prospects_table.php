<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('prospects', function (Blueprint $table) {
            $table->id();
            $table->enum('source', ['google_places'])->default('google_places');
            $table->string('place_id', 190)->unique();
            $table->string('name', 190);
            $table->text('formatted_address')->nullable();
            $table->text('short_address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('country', 100)->default('Indonesia');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('phone', 80)->nullable();
            $table->string('website', 255)->nullable();
            $table->text('google_maps_url')->nullable();
            $table->string('primary_type', 100)->nullable();
            $table->json('types_json')->nullable();
            $table->foreignId('keyword_id')->nullable()->constrained('ld_keywords')->nullOnDelete();
            $table->foreignId('grid_cell_id')->nullable()->constrained('ld_grid_cells')->nullOnDelete();
            $table->timestamp('discovered_at');
            $table->timestamp('last_seen_at')->nullable();
            $table->enum('status', ['new', 'assigned', 'converted', 'ignored'])->default('new');
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('converted_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->json('raw_json')->nullable();
            $table->timestamps();

            $table->index(['status', 'discovered_at']);
            $table->index(['city', 'province']);
            $table->index('owner_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospects');
    }
};

