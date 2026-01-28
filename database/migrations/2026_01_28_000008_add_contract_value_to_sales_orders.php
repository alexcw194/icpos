<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_orders', 'contract_value')) {
                $table->decimal('contract_value', 18, 2)->nullable()->after('total');
            }
        });

        if (Schema::hasColumn('sales_orders', 'contract_value')) {
            DB::table('sales_orders')
                ->whereNull('contract_value')
                ->update(['contract_value' => DB::raw('`total`')]);
        }
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            if (Schema::hasColumn('sales_orders', 'contract_value')) {
                $table->dropColumn('contract_value');
            }
        });
    }
};
