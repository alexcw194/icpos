<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("
            ALTER TABLE `so_billing_terms`
            MODIFY COLUMN `status`
            ENUM('planned','invoiced','paid','cancelled')
            NOT NULL DEFAULT 'planned'
        ");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::table('so_billing_terms')
            ->where('status', 'cancelled')
            ->update(['status' => 'planned']);

        DB::statement("
            ALTER TABLE `so_billing_terms`
            MODIFY COLUMN `status`
            ENUM('planned','invoiced','paid')
            NOT NULL DEFAULT 'planned'
        ");
    }
};
