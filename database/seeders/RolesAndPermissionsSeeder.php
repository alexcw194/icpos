<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $perms = [
            'customers.view','customers.create','customers.update','customers.delete',
            'items.view','items.create','items.update','items.delete',
            'quotations.view','quotations.create','quotations.update','quotations.delete',
            'purchases.view','purchases.create','purchases.post',
        ];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        $roles = [
            'SuperAdmin' => $perms,
            'Admin'      => $perms,
            'Sales'      => ['customers.*','items.view','quotations.*'],
            'Finance'    => ['purchases.view','purchases.post','quotations.view'],
        ];

        foreach ($roles as $roleName => $allowed) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $toAssign = collect($allowed)->flatMap(function ($entry) {
                if (str_ends_with($entry, '.*')) {
                    $prefix = substr($entry, 0, -2);
                    return Permission::where('name', 'like', $prefix . '.%')->pluck('name');
                }
                return [$entry];
            })->unique()->values()->all();
            $role->syncPermissions($toAssign);
        }
    }
}
