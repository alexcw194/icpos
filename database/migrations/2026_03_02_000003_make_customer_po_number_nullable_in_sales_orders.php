<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('sales_orders', 'customer_po_number')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                $table->string('customer_po_number')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sales_orders', 'customer_po_number')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                $table->string('customer_po_number')->nullable(false)->change();
            });
        }
    }
};
