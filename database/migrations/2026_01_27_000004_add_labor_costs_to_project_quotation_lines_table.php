<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('project_quotation_lines', function (Blueprint $t) {
            if (!Schema::hasColumn('project_quotation_lines', 'labor_id')) {
                $t->foreignId('labor_id')
                    ->nullable()
                    ->after('item_id')
                    ->constrained('labors')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('project_quotation_lines', 'labor_cost_amount')) {
                $t->decimal('labor_cost_amount', 18, 2)->nullable()->after('labor_override_reason');
            }
            if (!Schema::hasColumn('project_quotation_lines', 'labor_cost_source')) {
                $t->enum('labor_cost_source', ['sub_contractor', 'manual', 'none'])
                    ->nullable()
                    ->after('labor_cost_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('project_quotation_lines', function (Blueprint $t) {
            if (Schema::hasColumn('project_quotation_lines', 'labor_cost_source')) {
                $t->dropColumn('labor_cost_source');
            }
            if (Schema::hasColumn('project_quotation_lines', 'labor_cost_amount')) {
                $t->dropColumn('labor_cost_amount');
            }
            if (Schema::hasColumn('project_quotation_lines', 'labor_id')) {
                $t->dropConstrainedForeignId('labor_id');
            }
        });
    }
};
