<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('quotations', function (Blueprint $t) {
            if (!Schema::hasColumn('quotations','sales_order_id')) {
                $t->foreignId('sales_order_id')->nullable()->after('status')->constrained()->nullOnDelete();
            }
            if (!Schema::hasColumn('quotations','won_at')) {
                $t->timestamp('won_at')->nullable()->after('sales_order_id');
            }
        });
    }
    public function down(): void {
        Schema::table('quotations', function (Blueprint $t) {
            if (Schema::hasColumn('quotations','sales_order_id')) $t->dropConstrainedForeignId('sales_order_id');
            if (Schema::hasColumn('quotations','won_at')) $t->dropColumn('won_at');
        });
    }
};
