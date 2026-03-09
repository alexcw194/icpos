<?php

use App\Models\ItemVariant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('item_variants')) {
            return;
        }

        if (!Schema::hasColumn('item_variants', 'variant_key')) {
            Schema::table('item_variants', function (Blueprint $table) {
                $table->string('variant_key', 191)->default('__BASE__')->after('attributes');
            });
        }

        $seen = [];

        DB::table('item_variants')
            ->select(['id', 'item_id', 'attributes'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$seen) {
                foreach ($rows as $row) {
                    $attrs = json_decode((string) ($row->attributes ?? 'null'), true);
                    $attrs = is_array($attrs) ? $attrs : [];

                    $baseKey = ItemVariant::buildVariantKey($attrs);
                    $scopeKey = ((int) $row->item_id) . '|' . $baseKey;

                    $variantKey = $baseKey;
                    if (isset($seen[$scopeKey])) {
                        $suffix = '#dup-' . (int) $row->id;
                        $variantKey = substr($baseKey, 0, max(1, 191 - strlen($suffix))) . $suffix;
                    }
                    $seen[$scopeKey] = true;

                    DB::table('item_variants')
                        ->where('id', (int) $row->id)
                        ->update(['variant_key' => $variantKey]);
                }
            }, 'id');

        $hasUnique = $this->indexExists('item_variants', 'item_variants_item_variant_key_unique');

        if (Schema::hasColumn('item_variants', 'variant_key') && !$hasUnique) {
            Schema::table('item_variants', function (Blueprint $table) {
                $table->unique(['item_id', 'variant_key'], 'item_variants_item_variant_key_unique');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('item_variants')) {
            return;
        }

        $hasUnique = $this->indexExists('item_variants', 'item_variants_item_variant_key_unique');

        if (Schema::hasColumn('item_variants', 'variant_key') && $hasUnique) {
            Schema::table('item_variants', function (Blueprint $table) {
                $table->dropUnique('item_variants_item_variant_key_unique');
            });
        }

        if (Schema::hasColumn('item_variants', 'variant_key')) {
            Schema::table('item_variants', function (Blueprint $table) {
                $table->dropColumn('variant_key');
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $rows = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
            return !empty($rows);
        }

        if ($driver === 'pgsql') {
            $rows = DB::select('SELECT to_regclass(?) as idx', [$indexName]);
            return !empty($rows) && !empty($rows[0]->idx ?? null);
        }

        if ($driver === 'sqlite') {
            $rows = DB::select("PRAGMA index_list('{$table}')");
            foreach ($rows as $row) {
                if (($row->name ?? null) === $indexName) {
                    return true;
                }
            }
            return false;
        }

        return false;
    }
};
