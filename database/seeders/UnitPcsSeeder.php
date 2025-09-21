<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Unit;

class UnitPcsSeeder extends Seeder
{
    public function run(): void
    {
        // Buat/rapikan PCS (case-insensitive), selalu aktif
        $pcs = Unit::whereRaw('LOWER(code) = ?', ['pcs'])->first();
        if ($pcs) {
            $pcs->update(['code' => 'PCS', 'name' => $pcs->name ?: 'PCS', 'is_active' => true]);
        } else {
            Unit::create(['code' => 'PCS', 'name' => 'PCS', 'is_active' => true]);
        }
    }
}
