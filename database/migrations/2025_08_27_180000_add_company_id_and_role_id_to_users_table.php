<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add company_id if missing
        if (! Schema::hasColumn('users', 'company_id')) {
            Schema::table('users', function (Blueprint $t) {
                $t->unsignedBigInteger('company_id')->nullable()->after('id');
            });
        }

        // Add role_id if missing (legacy convenience column, non-FK)
        if (! Schema::hasColumn('users', 'role_id')) {
            Schema::table('users', function (Blueprint $t) {
                $t->unsignedBigInteger('role_id')->nullable()->index()->after('company_id');
            });
        }

        // Add FK company_id → companies.id if FK belum ada
        if (! $this->hasForeign('users', 'users_company_id_foreign') && Schema::hasColumn('users','company_id')) {
            try {
                Schema::table('users', function (Blueprint $t) {
                    $t->foreign('company_id')->references('id')->on('companies')
                      ->nullOnDelete()->cascadeOnUpdate();
                });
            } catch (\Throwable $e) {
                // ignore jika engine/row format berbeda atau FK sudah ada dengan nama lain
            }
        }
    }

    public function down(): void
    {
        // Drop FK if exists
        try { Schema::table('users', fn (Blueprint $t) => $t->dropForeign('users_company_id_foreign')); } catch (\Throwable $e) {}
        // Drop columns if exists
        if (Schema::hasColumn('users', 'role_id')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('role_id'));
        }
        if (Schema::hasColumn('users', 'company_id')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('company_id'));
        }
    }

    private function hasForeign(string $table, string $fk): bool
    {
        $db = DB::getDatabaseName();
        $row = DB::selectOne("
            SELECT COUNT(*) AS c FROM information_schema.key_column_usage
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?
        ", [$db, $table, $fk]);

        return (int)($row->c ?? 0) > 0;
    }
};
