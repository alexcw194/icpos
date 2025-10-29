<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $now   = now();
        $guard = config('auth.defaults.guard', 'web'); // Spatie expects guard_name

        $rows = [
            ['name' => 'Admin',        'guard_name' => $guard, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'SalesManager', 'guard_name' => $guard, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Sales',        'guard_name' => $guard, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Warehouse',    'guard_name' => $guard, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Finance',      'guard_name' => $guard, 'created_at' => $now, 'updated_at' => $now],
        ];

        // Spatie unique: (name, guard_name)
        DB::table('roles')->upsert(
            $rows,
            ['name', 'guard_name'],
            ['updated_at'] // only touch timestamp on conflict
        );
    }
}
