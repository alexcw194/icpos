<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('projects') || !Schema::hasTable('project_quotations') || !Schema::hasTable('sales_orders')) {
            return;
        }

        try {
            Artisan::call('projects:backfill-operational-so', [
                '--chunk' => 100,
            ]);
        } catch (\Throwable $e) {
            // Do not block schema migration if data backfill fails.
        }
    }

    public function down(): void
    {
        // Data backfill is intentionally not reverted.
    }
};

