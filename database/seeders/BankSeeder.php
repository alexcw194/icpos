<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class BankSeeder extends Seeder {
    public function run(): void
    {
        $now = Carbon::now();
        $rows = [
            ['code' => 'BCA',    'name' => 'Bank Central Asia',        'is_active' => true],
            ['code' => 'BRI',    'name' => 'Bank Rakyat Indonesia',   'is_active' => true],
        ];

        foreach ($rows as &$r) { $r['created_at'] = $now; $r['updated_at'] = $now; }
        DB::table('banks')->upsert($rows, ['code'], ['name','is_active','updated_at']);
    }
}