<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('item_variants', function (Blueprint $table) {
            // rolling cost (untuk margin)
            $table->decimal('last_cost', 18, 2)->nullable()->after('price');
            $table->decimal('avg_cost', 18, 2)->nullable()->after('last_cost');

            // opsional: baseline manual (kalau mau seed awal tanpa GR)
            $table->decimal('default_cost', 18, 2)->nullable()->after('avg_cost');
        });
    }

    public function down(): void
    {
        Schema::table('item_variants', function (Blueprint $table) {
            $table->dropColumn(['last_cost', 'avg_cost', 'default_cost']);
        });
    }
};
