<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Jalan hanya jika tabel items sudah ada
        if (!Schema::hasTable('items')) return;

        Schema::table('items', function (Blueprint $table) {
            if (!Schema::hasColumn('items', 'unit_id')) {
                $table->foreignId('unit_id')
                    ->nullable()              // sementara nullable agar aman untuk data lama
                    ->after('sku')            // sesuaikan posisi kolom bila perlu
                    ->constrained('units')    // referensi ke units.id
                    ->restrictOnDelete()      // cegah hapus unit yang sedang dipakai item
                    ->cascadeOnUpdate();      // update FK saat units.id berubah (jarang, tapi aman)
            }
        });

        // Backfill: set semua null ke unit 'pcs' (berdasarkan kolom code)
        $pcsId = DB::table('units')->where('code', 'pcs')->value('id');
        if ($pcsId) {
            DB::table('items')->whereNull('unit_id')->update(['unit_id' => $pcsId]);
        }
        // Catatan: setelah semua item punya unit_id, kamu bisa bikin migration lanjutan untuk set NOT NULL.
    }

    public function down(): void
    {
        if (!Schema::hasTable('items')) return;

        Schema::table('items', function (Blueprint $table) {
            if (Schema::hasColumn('items', 'unit_id')) {
                $table->dropConstrainedForeignId('unit_id'); // drop FK + kolom
            }
        });
    }
};
