<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('invoices', function (Blueprint $t) {
            if (!Schema::hasColumn('invoices','paid_at'))       $t->timestamp('paid_at')->nullable()->after('posted_at');
            if (!Schema::hasColumn('invoices','paid_amount'))   $t->decimal('paid_amount', 18, 2)->nullable()->after('paid_at');
            if (!Schema::hasColumn('invoices','paid_bank'))     $t->string('paid_bank', 100)->nullable()->after('paid_amount');
            if (!Schema::hasColumn('invoices','paid_ref'))      $t->string('paid_ref', 150)->nullable()->after('paid_bank');
            if (!Schema::hasColumn('invoices','payment_notes')) $t->text('payment_notes')->nullable()->after('paid_ref');
        });
    }
    public function down(): void {
        Schema::table('invoices', function (Blueprint $t) {
            foreach (['payment_notes','paid_ref','paid_bank','paid_amount','paid_at'] as $c) {
                if (Schema::hasColumn('invoices',$c)) $t->dropColumn($c);
            }
        });
    }
};
