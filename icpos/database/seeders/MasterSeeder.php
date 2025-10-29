<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Unit;
use App\Models\Jenis;
use App\Models\Brand;

class MasterSeeder extends Seeder
{
    public function run(): void
    {
        // ===== Units (sesuai struktur kamu: code, name) =====
        // Idempotent: aman di-run berulang
        $units = [
            'pcs'  => 'Pieces',
            'set'  => 'Set',
            'box'  => 'Box',
            'm'    => 'Meter',
            'kg'   => 'Kilogram',
            'ltr'  => 'Liter',
            'pack' => 'Pack',
        ];
        foreach ($units as $code => $name) {
            Unit::firstOrCreate(
                ['code' => strtolower($code)],
                ['name' => $name]
            );
        }

        // ===== Jenis =====
        // Sesuaikan dengan pasar ICP; slug & is_active otomatis
        $jenisList = [
            'Manufaktur', 'Hotel', 'Retail', 'F&B', 'Logistik',
            'Perkantoran', 'Pendidikan', 'Kesehatan', 'Pemerintahan', 'Lainnya'
        ];
        foreach ($jenisList as $name) {
            $slug = Str::slug($name);
            // jika slug sudah ada (karena soft-deletes), tambahkan suffix acak
            if (Jenis::withTrashed()->where('slug', $slug)->exists()) {
                $slug .= '-' . Str::random(4);
            }
            Jenis::firstOrCreate(
                ['name' => $name],
                ['slug' => $slug, 'is_active' => true]
            );
        }

        // ===== Brands =====
        $brands = ['PYROS', 'ROSENBAUER', 'FIREARMY', 'Generic'];
        foreach ($brands as $name) {
            $slug = Str::slug($name);
            if (Brand::withTrashed()->where('slug', $slug)->exists()) {
                $slug .= '-' . Str::random(4);
            }
            Brand::firstOrCreate(
                ['name' => $name],
                ['slug' => $slug, 'is_active' => true]
            );
        }
    }
}
