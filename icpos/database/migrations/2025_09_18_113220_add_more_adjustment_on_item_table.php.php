<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            // Grouping varian
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('items')->nullOnDelete();
            $table->string('family_code')->nullable()->after('sku')->index();

            // Tipe item & atribut fleksibel
            $table->string('item_type', 20)->default('standard')->after('description'); // standard|kit|cut_raw|cut_piece
            $table->json('attributes')->nullable()->after('item_type'); // mis. {"size":"M","color":"Navy"}

            // Khusus cutting
            $table->decimal('default_roll_length', 12, 2)->nullable()->after('attributes');   // untuk cut_raw
            $table->decimal('length_per_piece', 12, 2)->nullable()->after('default_roll_length'); // untuk cut_piece

            // Flag alur bisnis
            $table->boolean('sellable')->default(true)->after('length_per_piece');
            $table->boolean('purchasable')->default(true)->after('sellable');

            // Index kecil-kecilan
            $table->index(['item_type']);
            $table->index(['sellable']);
            $table->index(['purchasable']);
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn([
                'family_code',
                'item_type',
                'attributes',
                'default_roll_length',
                'length_per_piece',
                'sellable',
                'purchasable',
            ]);
        });
    }
};
