<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('project_quotation_payment_terms', function (Blueprint $table) {
            if (!Schema::hasColumn('project_quotation_payment_terms', 'due_trigger')) {
                $table->string('due_trigger', 32)->nullable()->after('percent');
            }
            if (!Schema::hasColumn('project_quotation_payment_terms', 'offset_days')) {
                $table->unsignedInteger('offset_days')->nullable()->after('due_trigger');
            }
            if (!Schema::hasColumn('project_quotation_payment_terms', 'day_of_month')) {
                $table->unsignedTinyInteger('day_of_month')->nullable()->after('offset_days');
            }
        });

        Schema::table('so_billing_terms', function (Blueprint $table) {
            if (!Schema::hasColumn('so_billing_terms', 'due_trigger')) {
                $table->string('due_trigger', 32)->nullable()->after('percent');
            }
            if (!Schema::hasColumn('so_billing_terms', 'offset_days')) {
                $table->unsignedInteger('offset_days')->nullable()->after('due_trigger');
            }
            if (!Schema::hasColumn('so_billing_terms', 'day_of_month')) {
                $table->unsignedTinyInteger('day_of_month')->nullable()->after('offset_days');
            }
        });
    }

    public function down(): void
    {
        Schema::table('project_quotation_payment_terms', function (Blueprint $table) {
            if (Schema::hasColumn('project_quotation_payment_terms', 'day_of_month')) {
                $table->dropColumn('day_of_month');
            }
            if (Schema::hasColumn('project_quotation_payment_terms', 'offset_days')) {
                $table->dropColumn('offset_days');
            }
            if (Schema::hasColumn('project_quotation_payment_terms', 'due_trigger')) {
                $table->dropColumn('due_trigger');
            }
        });

        Schema::table('so_billing_terms', function (Blueprint $table) {
            if (Schema::hasColumn('so_billing_terms', 'day_of_month')) {
                $table->dropColumn('day_of_month');
            }
            if (Schema::hasColumn('so_billing_terms', 'offset_days')) {
                $table->dropColumn('offset_days');
            }
            if (Schema::hasColumn('so_billing_terms', 'due_trigger')) {
                $table->dropColumn('due_trigger');
            }
        });
    }
};
