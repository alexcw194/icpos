<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->enum('po_type', ['goods','project','maintenance'])->default('goods')->after('customer_po_date');
        });

        DB::table('sales_orders')
            ->whereNull('po_type')
            ->update(['po_type' => 'goods']);
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn('po_type');
        });
    }
};
