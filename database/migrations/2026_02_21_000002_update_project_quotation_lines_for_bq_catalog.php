<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('project_quotation_lines', function (Blueprint $t) {
            if (!Schema::hasColumn('project_quotation_lines', 'line_type')) {
                $t->enum('line_type', ['product', 'charge', 'percent'])
                    ->default('product')
                    ->after('item_label');
            }

            if (!Schema::hasColumn('project_quotation_lines', 'catalog_id')) {
                $t->foreignId('catalog_id')
                    ->nullable()
                    ->after('line_type')
                    ->constrained('bq_line_catalogs')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('project_quotation_lines', 'percent_value')) {
                $t->decimal('percent_value', 9, 4)->nullable()->after('catalog_id');
            }

            if (!Schema::hasColumn('project_quotation_lines', 'percent_basis')) {
                $t->enum('percent_basis', ['product_subtotal', 'section_product_subtotal'])
                    ->nullable()
                    ->after('percent_value');
            }

            if (!Schema::hasColumn('project_quotation_lines', 'computed_amount')) {
                $t->decimal('computed_amount', 18, 2)->nullable()->after('percent_basis');
            }

            if (!Schema::hasColumn('project_quotation_lines', 'cost_bucket')) {
                $t->enum('cost_bucket', ['material', 'labor', 'overhead', 'other'])
                    ->default('overhead')
                    ->after('computed_amount');
            }
        });

        Schema::table('project_quotation_lines', function (Blueprint $t) {
            if (Schema::hasColumn('project_quotation_lines', 'source_template_id')) {
                $t->dropConstrainedForeignId('source_template_id');
            }
            if (Schema::hasColumn('project_quotation_lines', 'source_template_line_id')) {
                $t->dropConstrainedForeignId('source_template_line_id');
            }
            if (Schema::hasColumn('project_quotation_lines', 'basis_type')) {
                $t->dropColumn('basis_type');
            }
            if (Schema::hasColumn('project_quotation_lines', 'editable_price')) {
                $t->dropColumn('editable_price');
            }
            if (Schema::hasColumn('project_quotation_lines', 'editable_percent')) {
                $t->dropColumn('editable_percent');
            }
            if (Schema::hasColumn('project_quotation_lines', 'can_remove')) {
                $t->dropColumn('can_remove');
            }
        });
    }

    public function down(): void
    {
        Schema::table('project_quotation_lines', function (Blueprint $t) {
            if (Schema::hasColumn('project_quotation_lines', 'catalog_id')) {
                $t->dropConstrainedForeignId('catalog_id');
            }
            if (Schema::hasColumn('project_quotation_lines', 'line_type')) {
                $t->dropColumn('line_type');
            }
            if (Schema::hasColumn('project_quotation_lines', 'percent_value')) {
                $t->dropColumn('percent_value');
            }
            if (Schema::hasColumn('project_quotation_lines', 'percent_basis')) {
                $t->dropColumn('percent_basis');
            }
            if (Schema::hasColumn('project_quotation_lines', 'computed_amount')) {
                $t->dropColumn('computed_amount');
            }
            if (Schema::hasColumn('project_quotation_lines', 'cost_bucket')) {
                $t->dropColumn('cost_bucket');
            }
        });
    }
};
