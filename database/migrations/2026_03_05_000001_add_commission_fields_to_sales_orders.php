<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_orders', 'fee_amount')) {
                $table->decimal('fee_amount', 15, 2)->default(0)->after('private_notes');
            }

            if (!Schema::hasColumn('sales_orders', 'fee_paid_at')) {
                $table->date('fee_paid_at')->nullable()->after('under_amount');
            }

            if (!Schema::hasColumn('sales_orders', 'under_paid_at')) {
                $table->date('under_paid_at')->nullable()->after('fee_paid_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            if (Schema::hasColumn('sales_orders', 'under_paid_at')) {
                $table->dropColumn('under_paid_at');
            }
            if (Schema::hasColumn('sales_orders', 'fee_paid_at')) {
                $table->dropColumn('fee_paid_at');
            }
            if (Schema::hasColumn('sales_orders', 'fee_amount')) {
                $table->dropColumn('fee_amount');
            }
        });
    }
};
