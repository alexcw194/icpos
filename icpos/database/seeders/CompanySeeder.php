<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Company::firstOrCreate(
            ['name' => 'PT ICPOS Demo'], // cek berdasarkan name
            [
                'alias' => 'ICPOS',
                'city'  => 'Surabaya',
                'country' => 'Indonesia',
            ]
        );
    }
}
