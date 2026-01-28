<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_orders', 'payment_term_id')) {
                $table->foreignId('payment_term_id')
                    ->nullable()
                    ->after('po_type')
                    ->constrained('term_of_payments')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('sales_orders', 'payment_term_snapshot')) {
                $table->json('payment_term_snapshot')->nullable()->after('payment_term_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            if (Schema::hasColumn('sales_orders', 'payment_term_snapshot')) {
                $table->dropColumn('payment_term_snapshot');
            }
            if (Schema::hasColumn('sales_orders', 'payment_term_id')) {
                $table->dropConstrainedForeignId('payment_term_id');
            }
        });
    }
};
