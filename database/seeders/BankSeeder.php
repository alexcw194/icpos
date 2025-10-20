<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class BankSeeder extends Seeder {
    public function run(): void
    {
        $now  = Carbon::now();
        $rows = [
            ['code'=>'BCA PPN','name'=>'Bank Central Asia','account_name'=>'PT Indera Ciptapratama Perkasa','account_no'=>'6730322028','branch'=>'KCU Sudirman','is_active'=>true],
            ['code'=>'BCA NON','name'=>'Bank Central Asia','account_name'=>'Christian Widargo','account_no'=>'6730094220','branch'=>'KCP Thamrin','is_active'=>true],
            ['code'=>'BRI PPN','name'=>'Bank Rakyat Indonesia','account_name'=>'PT Indera Ciptapratama Perkasa,'account_no'=>'017201003158300','branch'=>null,'is_active'=>true],
        ];
        foreach ($rows as &$r) { $r['created_at']=$now; $r['updated_at']=$now; }

        DB::table('banks')->upsert(
            $rows,
            ['code'], // jadikan code unik (mis. "BCA PPN", "BCA NON")
            ['name','account_name','account_no','branch','is_active','updated_at']
        );
    }
}