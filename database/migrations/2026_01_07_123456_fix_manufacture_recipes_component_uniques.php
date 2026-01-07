<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('manufacture_recipes', function (Blueprint $table) {
            // drop unique lama (parent_item_id, component_item_id)
            $table->dropUnique(['parent_item_id', 'component_item_id']);

            // ubah component_item_id jadi nullable supaya row berbasis variant tidak bentrok
            $table->dropConstrainedForeignId('component_item_id');
            $table->foreignId('component_item_id')
                ->nullable()
                ->constrained('items')
                ->nullOnDelete()
                ->change();

            // unique untuk item-components (legacy / non-variant)
            $table->unique(['parent_item_id', 'component_item_id'], 'mfr_parent_component_item_unique');

            // unique untuk variant-components
            $table->unique(['parent_item_id', 'component_variant_id'], 'mfr_parent_component_variant_unique');
        });
    }

    public function down(): void
    {
        Schema::table('manufacture_recipes', function (Blueprint $table) {
            $table->dropUnique('mfr_parent_component_item_unique');
            $table->dropUnique('mfr_parent_component_variant_unique');

            $table->dropConstrainedForeignId('component_item_id');
            $table->foreignId('component_item_id')
                ->constrained('items')
                ->cascadeOnDelete();

            // balikin unique lama
            $table->unique(['parent_item_id', 'component_item_id']);
        });
    }
};
