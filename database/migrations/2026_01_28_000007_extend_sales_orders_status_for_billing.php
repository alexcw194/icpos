<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("
            ALTER TABLE `sales_orders`
            MODIFY COLUMN `status`
            ENUM(
                'open',
                'partial_delivered',
                'delivered',
                'invoiced',
                'closed',
                'cancelled',
                'partially_billed',
                'fully_billed'
            )
            NOT NULL DEFAULT 'open'
        ");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::table('sales_orders')
            ->whereIn('status', ['partially_billed', 'fully_billed'])
            ->update(['status' => 'open']);

        DB::statement("
            ALTER TABLE `sales_orders`
            MODIFY COLUMN `status`
            ENUM(
                'open',
                'partial_delivered',
                'delivered',
                'invoiced',
                'closed',
                'cancelled'
            )
            NOT NULL DEFAULT 'open'
        ");
    }
};
