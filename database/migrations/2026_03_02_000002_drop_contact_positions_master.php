<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('contacts', 'contact_position_id')) {
            Schema::table('contacts', function (Blueprint $table) {
                try {
                    $table->dropForeign(['contact_position_id']);
                } catch (\Throwable $e) {
                    // Ignore when foreign key does not exist.
                }

                $table->dropColumn('contact_position_id');
            });
        }

        Schema::dropIfExists('contact_positions');
    }

    public function down(): void
    {
        if (!Schema::hasTable('contact_positions')) {
            Schema::create('contact_positions', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasColumn('contacts', 'contact_position_id')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->foreignId('contact_position_id')->nullable()->constrained('contact_positions')->nullOnDelete();
            });
        }
    }
};
