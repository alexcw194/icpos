<?php

use Illuminate\Database\Migrations\Migration;   // <-- WAJIB
use Illuminate\Database\Schema\Blueprint;        // <-- WAJIB
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('items')) return;

        Schema::table('items', function (Blueprint $table) {
            if (!Schema::hasColumn('items','unit_id')) {
                $table->foreignId('unit_id')
                    ->nullable()                 // sementara nullable agar aman
                    ->after('sku')
                    ->constrained('units')
                    ->restrictOnDelete()
                    ->cascadeOnUpdate();
            }

            // Kalau fokus Step 2 hanya Unit, bagian di bawah boleh kamu hapus/skip dulu
            if (!Schema::hasColumn('items','brand_id')) {
                $table->foreignId('brand_id')
                    ->nullable()
                    ->after('unit_id')
                    ->constrained('brands')
                    ->restrictOnDelete()
                    ->cascadeOnUpdate();
            }
        });
    }

    public function down(): void {
        if (!Schema::hasTable('items')) return;

        Schema::table('items', function (Blueprint $table) {
            if (Schema::hasColumn('items','brand_id')) {
                $table->dropConstrainedForeignId('brand_id');
            }
            if (Schema::hasColumn('items','unit_id')) {
                $table->dropConstrainedForeignId('unit_id');
            }
        });
    }
};
