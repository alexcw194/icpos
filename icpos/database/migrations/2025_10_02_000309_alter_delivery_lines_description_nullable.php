<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Opsional: ubah string kosong jadi NULL biar konsisten
        \DB::table('delivery_lines')->where('description', '')->update(['description' => null]);

        Schema::table('delivery_lines', function (Blueprint $table) {
            $table->string('description')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_lines', function (Blueprint $table) {
            // Kembalikan ke NOT NULL; boleh beri default '' agar aman
            $table->string('description')->default('')->nullable(false)->change();
        });

        // Opsional: null-kan kembali ke string kosong
        \DB::table('delivery_lines')->whereNull('description')->update(['description' => '']);
    }
};