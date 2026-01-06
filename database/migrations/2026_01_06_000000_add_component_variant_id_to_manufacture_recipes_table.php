<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manufacture_recipes', function (Blueprint $table) {
            // relasi ke item_variants (nullable untuk backward-compat)
            $table->foreignId('component_variant_id')
                ->nullable()
                ->after('component_item_id')
                ->constrained('item_variants')
                ->nullOnDelete();

            // optional: index untuk query by variant
            $table->index(['parent_item_id', 'component_variant_id'], 'mfr_parent_component_variant_idx');
        });
    }

    public function down(): void
    {
        Schema::table('manufacture_recipes', function (Blueprint $table) {
            $table->dropIndex('mfr_parent_component_variant_idx');
            $table->dropConstrainedForeignId('component_variant_id');
        });
    }
};
