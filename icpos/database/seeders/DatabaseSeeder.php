<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            CompanySeeder::class,
            BootstrapAdminSeeder::class,
            RolesAndPermissionsSeeder::class,
            MasterSeeder::class,
            UnitPcsSeeder::class,
        ]);
    }
}
