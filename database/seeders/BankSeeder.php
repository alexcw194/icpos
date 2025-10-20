<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class BankSeeder extends Seeder {
    public function run(): void
    {
        $now = now();
        $rows = [
        ['company_id'=>1,'code'=>'BCA PPN','name'=>'Bank Central Asia','account_name'=>'PT Indera Ciptapratama Perkasa','account_no'=>'6730322028','is_active'=>true],
        ['company_id'=>1,'code'=>'BRI PPN','name'=>'Bank Rakyat Indonesia','account_name'=>'PT Indera Ciptapratama Perkasa','account_no'=>'017201003158300','is_active'=>true],
        ['company_id'=>2,'code'=>'BCA NON','name'=>'Bank Central Asia','account_name'=>'Christian Widargo','account_no'=>'6730094220','is_active'=>true],
        ];
        foreach ($rows as &$r) { $r['created_at']=$now; $r['updated_at']=$now; }
        DB::table('banks')->upsert($rows, ['company_id','code'], ['name','account_name','account_no','is_active','updated_at']);
    }
}