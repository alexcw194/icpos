<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'invoice_kind')) {
                $table->string('invoice_kind', 16)->nullable()->after('status');
            }
            if (!Schema::hasColumn('invoices', 'payment_schedule_seq')) {
                $table->unsignedInteger('payment_schedule_seq')->nullable()->after('sales_order_id');
            }
            if (!Schema::hasColumn('invoices', 'payment_schedule_meta')) {
                $table->json('payment_schedule_meta')->nullable()->after('payment_schedule_seq');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->unique(['sales_order_id','payment_schedule_seq'], 'invoices_so_schedule_unique');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            try { $table->dropUnique('invoices_so_schedule_unique'); } catch (Throwable $e) {}
            if (Schema::hasColumn('invoices', 'payment_schedule_meta')) {
                $table->dropColumn('payment_schedule_meta');
            }
            if (Schema::hasColumn('invoices', 'payment_schedule_seq')) {
                $table->dropColumn('payment_schedule_seq');
            }
            if (Schema::hasColumn('invoices', 'invoice_kind')) {
                $table->dropColumn('invoice_kind');
            }
        });
    }
};
