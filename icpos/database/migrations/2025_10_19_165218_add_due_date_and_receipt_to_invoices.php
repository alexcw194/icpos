<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'due_date')) {
                $table->date('due_date')->nullable()->after('date');
            }
            if (!Schema::hasColumn('invoices', 'receipt_path')) {
                $table->string('receipt_path')->nullable()->after('due_date');
            }
            if (!Schema::hasColumn('invoices', 'posted_at')) {
                $table->timestamp('posted_at')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'receipt_path')) {
                $table->dropColumn('receipt_path');
            }
            if (Schema::hasColumn('invoices', 'due_date')) {
                $table->dropColumn('due_date');
            }
            if (Schema::hasColumn('invoices', 'posted_at')) {
                $table->dropColumn('posted_at');
            }
        });
    }
};
