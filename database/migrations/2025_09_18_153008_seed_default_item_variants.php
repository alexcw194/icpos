<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;

return new class extends Migration {
    public function up(): void
    {
        // Guard: kolom-kolom opsional di items
        $hasPriceCol       = Schema::hasColumn('items', 'price');
        $hasVariantTypeCol = Schema::hasColumn('items', 'variant_type');
        // Legacy only (tidak dipakai; stok modern di item_stocks)
        $hasLegacyStockCol = Schema::hasColumn('items', 'stock');

        // Set default variant_type jika kolom ada
        if ($hasVariantTypeCol) {
            DB::table('items')->whereNull('variant_type')->update(['variant_type' => 'none']);
        }

        $now     = Carbon::now();
        $lastId  = 0;

        do {
            // Build select list dinamis sesuai kolom yang ada
            $selectCols = ['id', 'sku'];
            if ($hasPriceCol)       { $selectCols[] = 'price'; }
            if ($hasVariantTypeCol) { $selectCols[] = 'variant_type'; }
            if ($hasLegacyStockCol) { $selectCols[] = 'stock'; } // legacy only (tidak akan dipakai untuk persist)

            $items = DB::table('items')
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit(500)
                ->get($selectCols);

            if ($items->isEmpty()) {
                break;
            }

            foreach ($items as $it) {
                $lastId = $it->id;

                // Skip jika sudah punya varian
                $exists = DB::table('item_variants')->where('item_id', $it->id)->exists();
                if ($exists) {
                    continue;
                }

                DB::table('item_variants')->insert([
                    'item_id'     => $it->id,
                    'sku'         => $it->sku ?? null,
                    'price'       => (float)($it->price ?? 0),
                    // Stok varian diinisialisasi 0 — stok riil dikelola via Goods Receipt (stock_ledgers/item_stocks)
                    'stock'       => 0,
                    'attributes'  => null,     // single-SKU (tanpa atribut)
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
        // Rollback konservatif: hapus varian yang tidak memiliki attributes (dibuat oleh seed ini)
        DB::table('item_variants')->whereNull('attributes')->delete();
        // Biarkan items.variant_type apa adanya (non-destructive)
    }
};
