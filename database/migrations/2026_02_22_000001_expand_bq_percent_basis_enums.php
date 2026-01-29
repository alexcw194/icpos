<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('bq_line_catalogs') && Schema::hasColumn('bq_line_catalogs', 'percent_basis')) {
            DB::statement(
                "ALTER TABLE bq_line_catalogs MODIFY COLUMN percent_basis "
                ."ENUM('product_subtotal','section_product_subtotal','material_subtotal','section_material_subtotal') "
                ."NOT NULL DEFAULT 'product_subtotal'"
            );
        }

        if (Schema::hasTable('project_quotation_lines') && Schema::hasColumn('project_quotation_lines', 'percent_basis')) {
            DB::statement(
                "ALTER TABLE project_quotation_lines MODIFY COLUMN percent_basis "
                ."ENUM('product_subtotal','section_product_subtotal','material_subtotal','section_material_subtotal') "
                ."NULL DEFAULT NULL"
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bq_line_catalogs') && Schema::hasColumn('bq_line_catalogs', 'percent_basis')) {
            DB::statement(
                "ALTER TABLE bq_line_catalogs MODIFY COLUMN percent_basis "
                ."ENUM('product_subtotal','section_product_subtotal') "
                ."NOT NULL DEFAULT 'product_subtotal'"
            );
        }

        if (Schema::hasTable('project_quotation_lines') && Schema::hasColumn('project_quotation_lines', 'percent_basis')) {
            DB::statement(
                "ALTER TABLE project_quotation_lines MODIFY COLUMN percent_basis "
                ."ENUM('product_subtotal','section_product_subtotal') "
                ."NULL DEFAULT NULL"
            );
        }
    }
};
