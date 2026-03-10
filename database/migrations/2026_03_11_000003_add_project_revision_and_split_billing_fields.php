<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_orders')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('sales_orders', 'project_billing_mode')) {
                    $table->string('project_billing_mode', 32)
                        ->default('combined')
                        ->after('project_name');
                }
            });

            DB::table('sales_orders')
                ->whereNull('project_billing_mode')
                ->update(['project_billing_mode' => 'combined']);
        }

        if (Schema::hasTable('project_quotations')) {
            Schema::table('project_quotations', function (Blueprint $table) {
                if (!Schema::hasColumn('project_quotations', 'parent_revision_id')) {
                    $table->foreignId('parent_revision_id')
                        ->nullable()
                        ->after('project_id')
                        ->constrained('project_quotations')
                        ->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('project_quotation_lines')) {
            Schema::table('project_quotation_lines', function (Blueprint $table) {
                if (!Schema::hasColumn('project_quotation_lines', 'revision_source_line_id')) {
                    $table->foreignId('revision_source_line_id')
                        ->nullable()
                        ->after('section_id')
                        ->constrained('project_quotation_lines')
                        ->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('sales_order_lines')) {
            Schema::table('sales_order_lines', function (Blueprint $table) {
                if (!Schema::hasColumn('sales_order_lines', 'material_total')) {
                    $table->decimal('material_total', 18, 2)
                        ->default(0)
                        ->after('unit_price');
                }
                if (!Schema::hasColumn('sales_order_lines', 'labor_total')) {
                    $table->decimal('labor_total', 18, 2)
                        ->default(0)
                        ->after('material_total');
                }
                if (!Schema::hasColumn('sales_order_lines', 'baseline_material_total')) {
                    $table->decimal('baseline_material_total', 18, 2)
                        ->nullable()
                        ->after('baseline_unit_price');
                }
                if (!Schema::hasColumn('sales_order_lines', 'baseline_labor_total')) {
                    $table->decimal('baseline_labor_total', 18, 2)
                        ->nullable()
                        ->after('baseline_material_total');
                }
            });

            DB::table('sales_order_lines')
                ->whereNull('material_total')
                ->update(['material_total' => DB::raw('COALESCE(line_total, 0)')]);

            DB::table('sales_order_lines')
                ->whereNull('labor_total')
                ->update(['labor_total' => 0]);

            DB::table('sales_order_lines')
                ->whereNull('baseline_material_total')
                ->update(['baseline_material_total' => DB::raw('COALESCE(baseline_line_total, 0)')]);

            DB::table('sales_order_lines')
                ->whereNull('baseline_labor_total')
                ->update(['baseline_labor_total' => 0]);
        }

        if (Schema::hasTable('billing_documents')) {
            Schema::table('billing_documents', function (Blueprint $table) {
                if (!Schema::hasColumn('billing_documents', 'billing_component')) {
                    $table->string('billing_component', 20)
                        ->default('combined')
                        ->after('mode');
                }
            });
            DB::table('billing_documents')
                ->whereNull('billing_component')
                ->update(['billing_component' => 'combined']);
        }

        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                if (!Schema::hasColumn('invoices', 'billing_component')) {
                    $table->string('billing_component', 20)
                        ->default('combined')
                        ->after('so_billing_term_id');
                }
            });
            DB::table('invoices')
                ->whereNull('billing_component')
                ->update(['billing_component' => 'combined']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                if (Schema::hasColumn('invoices', 'billing_component')) {
                    $table->dropColumn('billing_component');
                }
            });
        }

        if (Schema::hasTable('billing_documents')) {
            Schema::table('billing_documents', function (Blueprint $table) {
                if (Schema::hasColumn('billing_documents', 'billing_component')) {
                    $table->dropColumn('billing_component');
                }
            });
        }

        if (Schema::hasTable('sales_order_lines')) {
            Schema::table('sales_order_lines', function (Blueprint $table) {
                if (Schema::hasColumn('sales_order_lines', 'baseline_labor_total')) {
                    $table->dropColumn('baseline_labor_total');
                }
                if (Schema::hasColumn('sales_order_lines', 'baseline_material_total')) {
                    $table->dropColumn('baseline_material_total');
                }
                if (Schema::hasColumn('sales_order_lines', 'labor_total')) {
                    $table->dropColumn('labor_total');
                }
                if (Schema::hasColumn('sales_order_lines', 'material_total')) {
                    $table->dropColumn('material_total');
                }
            });
        }

        if (Schema::hasTable('project_quotation_lines')) {
            Schema::table('project_quotation_lines', function (Blueprint $table) {
                if (Schema::hasColumn('project_quotation_lines', 'revision_source_line_id')) {
                    $table->dropConstrainedForeignId('revision_source_line_id');
                }
            });
        }

        if (Schema::hasTable('project_quotations')) {
            Schema::table('project_quotations', function (Blueprint $table) {
                if (Schema::hasColumn('project_quotations', 'parent_revision_id')) {
                    $table->dropConstrainedForeignId('parent_revision_id');
                }
            });
        }

        if (Schema::hasTable('sales_orders')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                if (Schema::hasColumn('sales_orders', 'project_billing_mode')) {
                    $table->dropColumn('project_billing_mode');
                }
            });
        }
    }
};

