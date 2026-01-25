<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('items')) {
            return;
        }

        if (!Schema::hasColumn('items', 'list_type')) {
            Schema::table('items', function (Blueprint $table) {
                $table->enum('list_type', ['retail', 'project'])
                    ->default('retail')
                    ->after('brand_id');
                $table->index('list_type');
            });
        }

        // Backfill legacy project item_type to list_type=project (if any).
        DB::table('items')
            ->where('item_type', 'project')
            ->update(['list_type' => 'project']);

        // Ensure any null list_type is normalized to retail.
        DB::table('items')
            ->whereNull('list_type')
            ->update(['list_type' => 'retail']);
    }

    public function down(): void
    {
        if (!Schema::hasTable('items') || !Schema::hasColumn('items', 'list_type')) {
            return;
        }

        Schema::table('items', function (Blueprint $table) {
            try {
                $table->dropIndex('items_list_type_index');
            } catch (\Throwable $e) {
            }
            $table->dropColumn('list_type');
        });
    }
};
