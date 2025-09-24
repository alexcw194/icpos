<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // ===========================
        // 1) DAFTAR PERMISSIONS
        // ===========================
        $perms = [
            // master data
            'customers.view','customers.create','customers.update','customers.delete',
            'items.view','items.create','items.update','items.delete',

            // dokumen
            'quotations.view','quotations.create','quotations.update','quotations.delete',
            'quotations.send','quotations.mark_po',

            'invoices.view','invoices.create','invoices.update','invoices.delete',
            'deliveries.view','deliveries.create','deliveries.update','deliveries.delete','deliveries.post','deliveries.cancel',

            // admin modules
            'companies.manage','users.manage',

            // global settings (khusus SuperAdmin)
            'settings.manage',
        ];

        foreach ($perms as $p) {
            Permission::findOrCreate($p, 'web');
        }

        // ===========================
        // 2) ROLES
        // ===========================
        // Tambahkan SuperAdmin (punya semua permission)
        $super   = Role::findOrCreate('SuperAdmin', 'web');

        // Roles yang sudah kamu pakai
        $admin   = Role::findOrCreate('Admin', 'web');
        $sales   = Role::findOrCreate('Sales', 'web');
        $finance = Role::findOrCreate('Finance', 'web');

        // ===========================
        // 3) ASSIGN PERMISSIONS
        // ===========================

        // SuperAdmin: semua permission guard web
        $allWebPerms = Permission::where('guard_name','web')->pluck('name')->all();
        $super->syncPermissions($allWebPerms);

        // Admin: semua KECUALI settings.manage
        $adminPerms = array_values(array_diff($allWebPerms, ['settings.manage']));
        $admin->syncPermissions($adminPerms);

        // Sales: terbatas (tetap seperti rencana)
        $sales->syncPermissions([
            'customers.view','customers.create','customers.update',
            'items.view',
            'quotations.view','quotations.create','quotations.update','quotations.send',
            'invoices.view','invoices.create',
            'deliveries.view','deliveries.create','deliveries.post',
        ]);

        // Finance: fokus quotation PO, invoice & delivery
        $finance->syncPermissions([
            'customers.view',
            'items.view',
            'quotations.view','quotations.mark_po',
            'invoices.view','invoices.create','invoices.update','invoices.delete',
            'deliveries.view','deliveries.create','deliveries.update','deliveries.delete','deliveries.post','deliveries.cancel',
        ]);

        // ===========================
        // 4) (OPSIONAL) Tunjuk SuperAdmin
        // ===========================
        // Kalau mau otomatis menetapkan kamu sebagai SuperAdmin,
        // isi email-mu di env: SUPERADMIN_EMAIL=you@example.com
        // atau ubah langsung string di bawah.
        $email = env('mail@inderacipta.com'); // misal: 'kamu@domain.com'
        if ($email) {
            $userClass = config('auth.providers.users.model', \App\Models\User::class);
            if (class_exists($userClass)) {
                $user = $userClass::where('email', $email)->first();
                if ($user) {
                    $user->syncRoles(['SuperAdmin']); // hanya kamu yang SuperAdmin
                }
            }
        }
    }
}

