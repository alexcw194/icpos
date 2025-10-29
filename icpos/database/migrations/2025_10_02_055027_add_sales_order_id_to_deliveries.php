<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('deliveries', function (Blueprint $table) {
            if (!Schema::hasColumn('deliveries','sales_order_id')) {
                $table->foreignId('sales_order_id')
                      ->nullable()
                      ->after('quotation_id')
                      ->constrained('sales_orders')
                      ->nullOnDelete();
                $table->index('sales_order_id');
            }
        });
    }
    public function down(): void {
        Schema::table('deliveries', function (Blueprint $table) {
            if (Schema::hasColumn('deliveries','sales_order_id')) {
                $table->dropConstrainedForeignId('sales_order_id');
            }
        });
    }
};