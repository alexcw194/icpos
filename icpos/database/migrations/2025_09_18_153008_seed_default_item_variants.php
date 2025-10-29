<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

return new class extends Migration {
    public function up(): void
    {
        // Pastikan semua item punya variant_type minimal 'none'
        DB::table('items')->whereNull('variant_type')->update(['variant_type' => 'none']);

        $now = Carbon::now();

        // Ambil items batch per 500 untuk aman
        $lastId = 0;
        do {
            $items = DB::table('items')
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit(500)
                ->get(['id', 'sku', 'price', 'stock', 'variant_type']);

            if ($items->isEmpty()) break;

            foreach ($items as $it) {
                $lastId = $it->id;

                // kalau sudah punya varian, skip
                $exists = DB::table('item_variants')->where('item_id', $it->id)->exists();
                if ($exists) continue;

                DB::table('item_variants')->insert([
                    'item_id'     => $it->id,
                    'sku'         => $it->sku,                // boleh null
                    'price'       => (float)($it->price ?? 0),
                    'stock'       => (int)  ($it->stock ?? 0),
                    'attributes'  => null,                    // single SKU (no attributes)
                    'is_active'   => true,
                    'barcode'     => null,
                    'min_stock'   => 0,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }
        } while (true);
    }

    public function down(): void
    {
        // Rollback: Hapus varian yang kosong atributnya & item masih ada
        DB::table('item_variants')->whereNull('attributes')->delete();
        // variant_type yang tadinya null biarkan saja (non destructive)
    }
};
