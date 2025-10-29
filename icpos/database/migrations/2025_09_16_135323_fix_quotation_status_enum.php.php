<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1) Izinkan 'won' sementara TANPA menghapus 'po' agar update data bisa jalan
        DB::statement("ALTER TABLE quotations
            MODIFY COLUMN status ENUM('draft','sent','po','won') NOT NULL DEFAULT 'draft'");

        // 2) Migrasi data lama
        DB::table('quotations')->where('status', 'po')->update(['status' => 'won']);

        // 3) Rapikan: hapus 'po' dari definisi enum
        DB::statement("ALTER TABLE quotations
            MODIFY COLUMN status ENUM('draft','sent','won') NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        // Kembalikan enum lama & data (optional)
        DB::statement("ALTER TABLE quotations
            MODIFY COLUMN status ENUM('draft','sent','po') NOT NULL DEFAULT 'draft'");
        DB::table('quotations')->where('status', 'won')->update(['status' => 'po']);
    }
};
