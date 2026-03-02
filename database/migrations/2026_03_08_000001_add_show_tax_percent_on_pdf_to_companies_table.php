<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('companies')) {
            return;
        }

        Schema::table('companies', function (Blueprint $table) {
            if (!Schema::hasColumn('companies', 'show_tax_percent_on_pdf')) {
                $table->boolean('show_tax_percent_on_pdf')
                    ->default(true)
                    ->after('default_tax_percent');
            }
        });

        if (Schema::hasColumn('companies', 'show_tax_percent_on_pdf')) {
            DB::table('companies')
                ->whereNull('show_tax_percent_on_pdf')
                ->update(['show_tax_percent_on_pdf' => true]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('companies')) {
            return;
        }

        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'show_tax_percent_on_pdf')) {
                $table->dropColumn('show_tax_percent_on_pdf');
            }
        });
    }
};

