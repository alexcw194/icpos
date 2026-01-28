<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'sales_order_id')) {
                $table->foreignId('sales_order_id')
                    ->nullable()
                    ->after('quotation_id')
                    ->constrained('sales_orders')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('invoices', 'so_billing_term_id')) {
                $table->foreignId('so_billing_term_id')
                    ->nullable()
                    ->after('sales_order_id')
                    ->constrained('so_billing_terms')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'so_billing_term_id')) {
                $table->dropConstrainedForeignId('so_billing_term_id');
            }
            if (Schema::hasColumn('invoices', 'sales_order_id')) {
                $table->dropConstrainedForeignId('sales_order_id');
            }
        });
    }
};
