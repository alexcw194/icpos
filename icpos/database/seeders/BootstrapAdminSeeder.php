<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Company;
use App\Models\Role;

class BootstrapAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $company = Company::first();               // Ambil PT ICPOS Demo
        $adminRole = Role::where('name','Admin')->first();

        // Ambil user pertama yang sudah ada di DB
        $user = User::orderBy('id')->first();

        if ($user && $company && $adminRole) {
            $user->company_id = $company->id;
            $user->role_id    = $adminRole->id;
            $user->save();
        }
    }
}

