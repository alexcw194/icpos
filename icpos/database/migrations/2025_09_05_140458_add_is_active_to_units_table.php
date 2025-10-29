<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    private function indexExists(string $table, string $index): bool
    {
        $driver = config('database.default');
        $drv = config("database.connections.$driver.driver");

        if ($drv === 'mysql') {
            $res = DB::select("SHOW INDEX FROM `$table` WHERE Key_name = ?", [$index]);
            return !empty($res);
        }
        if ($drv === 'sqlite') {
            $res = DB::select("PRAGMA index_list('$table')");
            return collect($res)->contains(fn($r) => ($r->name ?? $r->seq ?? '') === $index || ($r->name ?? '') === $index);
        }
        // fallback
        return false;
    }

    public function up(): void
    {
        // Tambah kolom kalau belum ada
        if (!Schema::hasColumn('units', 'is_active')) {
            Schema::table('units', function (Blueprint $t) {
                $t->boolean('is_active')->default(true)->after('name');
            });
        }

        // Set semua NULL -> true (kalau kolom sudah ada tapi null)
        if (Schema::hasColumn('units', 'is_active')) {
            DB::table('units')->whereNull('is_active')->update(['is_active' => true]);
        }

        // Tambah index kalau belum ada
        if (!$this->indexExists('units', 'units_is_active_idx')) {
            Schema::table('units', function (Blueprint $t) {
                $t->index('is_active', 'units_is_active_idx');
            });
        }
    }

    public function down(): void
    {
        // Drop index jika ada
        if ($this->indexExists('units', 'units_is_active_idx')) {
            Schema::table('units', function (Blueprint $t) {
                $t->dropIndex('units_is_active_idx');
            });
        }

        // Drop kolom jika ada
        if (Schema::hasColumn('units', 'is_active')) {
            Schema::table('units', function (Blueprint $t) {
                $t->dropColumn('is_active');
            });
        }
    }
};
