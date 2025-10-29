<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Company;
use Spatie\Permission\Models\Role;

class BootstrapAdminSeeder extends Seeder
{
    public function run(): void
    {
        // 1) Pastikan role SuperAdmin exist (Spatie)
        $role = Role::firstOrCreate(['name' => 'SuperAdmin', 'guard_name' => 'web']);

        // 2) Pastikan default company exist
        $company = Company::firstOrCreate(
            ['name' => 'PT Indera Ciptapratama Perkasa'],
            [
                'alias' => 'ICP',
                'email' => 'mail@inderacipta.com',
                'phone' => null,
                'address' => 'Jalan Prapanca 40-A, Surabaya, Jawa Timur 60241',
                'is_default' => true,
                'is_taxable' => true,
                'default_tax_percent' => 11,
            ]
        );

        // 3) Create / update SuperAdmin user (email target)
        $email = 'mail@inderacipta.com';

        /** @var \App\Models\User $user */
        $user = User::where('email', $email)->first();

        if (! $user) {
            $user = User::create([
                'name'              => 'ICP SuperAdmin',
                'email'             => $email,
                'password'          => Hash::make('Indera123!'), // ganti setelah login
                'email_verified_at' => now(),
                'company_id'        => $company->id,
                // optional convenience column if you still keep users.role_id (tanpa FK)
                'role_id'           => null,
                'remember_token'    => Str::random(10),
            ]);
        } else {
            // keep existing password; just ensure linkage & verified
            $user->forceFill([
                'company_id'        => $company->id,
                'email_verified_at' => $user->email_verified_at ?: now(),
            ])->save();
        }

        // 4) Assign Spatie role (pivot model_has_roles)
        $user->syncRoles(['SuperAdmin']);

        // (Opsional) Tandai sebagai admin default di profil milikmu jika ada kolom khusus lain
        // $user->is_active = true; $user->save();
    }
}
