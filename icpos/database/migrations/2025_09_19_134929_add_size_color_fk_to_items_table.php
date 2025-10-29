<?php

// database/migrations/2025_09_19_000003_add_size_color_fk_to_items_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('items')) return;

        Schema::table('items', function (Blueprint $table) {
            if (!Schema::hasColumn('items', 'size_id')) {
                $table->foreignId('size_id')->nullable()->after('brand_id')
                    ->constrained('sizes')->nullOnDelete()->cascadeOnUpdate();
            }
            if (!Schema::hasColumn('items', 'color_id')) {
                $table->foreignId('color_id')->nullable()->after('size_id')
                    ->constrained('colors')->nullOnDelete()->cascadeOnUpdate();
            }
        });

        // (Opsional) kalau dulu ada kolom string 'size' / 'color', kamu bisa mapping otomatis:
        // DB::statement('UPDATE items i JOIN sizes s ON i.size = s.name SET i.size_id = s.id');
        // DB::statement('UPDATE items i JOIN colors c ON i.color = c.name SET i.color_id = c.id');
    }

    public function down(): void {
        if (!Schema::hasTable('items')) return;
        Schema::table('items', function (Blueprint $table) {
            if (Schema::hasColumn('items','color_id')) $table->dropConstrainedForeignId('color_id');
            if (Schema::hasColumn('items','size_id'))  $table->dropConstrainedForeignId('size_id');
        });
    }
};
