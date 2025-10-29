<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('sales_orders', function (Blueprint $t) {
            $t->text('private_notes')->nullable()->after('notes');   // catatan pribadi
            $t->decimal('under_amount', 15, 2)->default(0)->after('private_notes'); // "Under"
        });
    }
    public function down(): void {
        Schema::table('sales_orders', function (Blueprint $t) {
            $t->dropColumn(['private_notes','under_amount']);
        });
    }
};
