<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $guard = config('auth.defaults.guard', 'web');

        DB::table('roles')->updateOrInsert(
            ['name' => 'Dokumen', 'guard_name' => $guard],
            ['updated_at' => $now, 'created_at' => $now]
        );
    }

    public function down(): void
    {
        DB::table('roles')
            ->where('name', 'Dokumen')
            ->where('guard_name', config('auth.defaults.guard', 'web'))
            ->delete();
    }
};
