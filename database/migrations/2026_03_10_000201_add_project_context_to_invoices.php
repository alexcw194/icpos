<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('invoices')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'project_id')) {
                $table->foreignId('project_id')
                    ->nullable()
                    ->after('quotation_id')
                    ->constrained('projects')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('invoices', 'project_quotation_id')) {
                $table->foreignId('project_quotation_id')
                    ->nullable()
                    ->after('project_id')
                    ->constrained('project_quotations')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('invoices', 'project_payment_term_id')) {
                $table->foreignId('project_payment_term_id')
                    ->nullable()
                    ->after('project_quotation_id')
                    ->constrained('project_quotation_payment_terms')
                    ->nullOnDelete();
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            try {
                $table->unique('project_payment_term_id', 'invoices_project_payment_term_unique');
            } catch (\Throwable $e) {
                // Already exists.
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('invoices')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            try {
                $table->dropUnique('invoices_project_payment_term_unique');
            } catch (\Throwable $e) {
                // Ignore.
            }

            if (Schema::hasColumn('invoices', 'project_payment_term_id')) {
                $table->dropConstrainedForeignId('project_payment_term_id');
            }
            if (Schema::hasColumn('invoices', 'project_quotation_id')) {
                $table->dropConstrainedForeignId('project_quotation_id');
            }
            if (Schema::hasColumn('invoices', 'project_id')) {
                $table->dropConstrainedForeignId('project_id');
            }
        });
    }
};

