<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_order_lines', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_order_lines', 'po_item_name')) {
                $table->string('po_item_name', 255)->nullable()->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_order_lines', function (Blueprint $table) {
            if (Schema::hasColumn('sales_order_lines', 'po_item_name')) {
                $table->dropColumn('po_item_name');
            }
        });
    }
};
