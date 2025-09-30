<?php

// database/migrations/xxxx_add_draft_columns_to_sales_orders.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('sales_orders', function (Blueprint $t) {
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // opsional:
            // $t->timestamp('expires_at')->nullable()->index();
            // pastikan so_number nullable saat draft:
        });
    }
    public function down(): void {
        Schema::table('sales_orders', function (Blueprint $t) {
            // kembalikan sesuai kondisi semula
            $t->dropConstrainedForeignId('created_by');
            // $t->dropColumn('expires_at');
        });
    }
};
