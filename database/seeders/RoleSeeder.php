<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            ['name' => 'Admin',        'label' => 'Full access'],
            ['name' => 'SalesManager', 'label' => 'Manage sales & quotations'],
            ['name' => 'Sales',        'label' => 'Create quotations, customers'],
            ['name' => 'Finance',      'label' => 'Approve quotations'],
            ['name' => 'Viewer',       'label' => 'Read only access'],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role['name']], $role);
        }
    }
}
