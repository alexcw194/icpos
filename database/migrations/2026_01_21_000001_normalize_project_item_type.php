<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('items', 'item_type')) {
            return;
        }

        // Normalize legacy project item_type values to standard.
        $updated = DB::table('items')
            ->where('item_type', 'project')
            ->update(['item_type' => 'standard']);

        if ($updated > 0) {
            Log::info('Normalized items.item_type from project to standard.', ['count' => $updated]);
        }
    }

    public function down(): void
    {
        // No safe rollback: original project item types are not tracked.
    }
};
