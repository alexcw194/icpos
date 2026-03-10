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
                if (!Schema::hasColumn('sales_orders', 'project_quotation_id')) {
                    $table->foreignId('project_quotation_id')
                        ->nullable()
                        ->after('project_id')
                        ->constrained('project_quotations')
                        ->nullOnDelete();
                }

                if (!Schema::hasColumn('sales_orders', 'customer_ref_type')) {
                    $table->string('customer_ref_type', 20)
                        ->default('po')
                        ->after('customer_po_date');
                }
            });

            DB::table('sales_orders')
                ->whereNull('customer_ref_type')
                ->update(['customer_ref_type' => 'po']);
        }

        if (Schema::hasTable('sales_order_lines')) {
            Schema::table('sales_order_lines', function (Blueprint $table) {
                if (!Schema::hasColumn('sales_order_lines', 'baseline_project_quotation_line_id')) {
                    $table->unsignedBigInteger('baseline_project_quotation_line_id')
                        ->nullable()
                        ->after('item_variant_id');
                    $table->index('baseline_project_quotation_line_id', 'sol_baseline_pql_idx');
                }
                if (!Schema::hasColumn('sales_order_lines', 'baseline_name')) {
                    $table->string('baseline_name', 255)->nullable()->after('baseline_project_quotation_line_id');
                }
                if (!Schema::hasColumn('sales_order_lines', 'baseline_description')) {
                    $table->text('baseline_description')->nullable()->after('baseline_name');
                }
                if (!Schema::hasColumn('sales_order_lines', 'baseline_item_id')) {
                    $table->foreignId('baseline_item_id')
                        ->nullable()
                        ->after('baseline_description')
                        ->constrained('items')
                        ->nullOnDelete();
                }
                if (!Schema::hasColumn('sales_order_lines', 'baseline_item_variant_id')) {
                    $table->foreignId('baseline_item_variant_id')
                        ->nullable()
                        ->after('baseline_item_id')
                        ->constrained('item_variants')
                        ->nullOnDelete();
                }
                if (!Schema::hasColumn('sales_order_lines', 'baseline_qty')) {
                    $table->decimal('baseline_qty', 18, 4)->nullable()->after('baseline_item_variant_id');
                }
                if (!Schema::hasColumn('sales_order_lines', 'baseline_unit')) {
                    $table->string('baseline_unit', 20)->nullable()->after('baseline_qty');
                }
                if (!Schema::hasColumn('sales_order_lines', 'baseline_unit_price')) {
                    $table->decimal('baseline_unit_price', 18, 2)->nullable()->after('baseline_unit');
                }
                if (!Schema::hasColumn('sales_order_lines', 'baseline_line_total')) {
                    $table->decimal('baseline_line_total', 18, 2)->nullable()->after('baseline_unit_price');
                }
            });
        }

        if (Schema::hasTable('purchase_order_lines')) {
            Schema::table('purchase_order_lines', function (Blueprint $table) {
                if (!Schema::hasColumn('purchase_order_lines', 'sales_order_line_id')) {
                    $table->foreignId('sales_order_line_id')
                        ->nullable()
                        ->after('purchase_order_id')
                        ->constrained('sales_order_lines')
                        ->nullOnDelete();
                    $table->index('sales_order_line_id', 'pol_so_line_idx');
                }
            });
        }

        if (Schema::hasTable('billing_documents')) {
            Schema::table('billing_documents', function (Blueprint $table) {
                if (!Schema::hasColumn('billing_documents', 'so_billing_term_id')) {
                    $table->foreignId('so_billing_term_id')
                        ->nullable()
                        ->after('sales_order_id')
                        ->constrained('so_billing_terms')
                        ->nullOnDelete();
                    $table->index(['sales_order_id', 'so_billing_term_id'], 'billings_so_term_idx');
                }
            });
        }

        if (Schema::hasTable('sales_order_attachments')) {
            Schema::table('sales_order_attachments', function (Blueprint $table) {
                if (!Schema::hasColumn('sales_order_attachments', 'category')) {
                    $table->string('category', 32)
                        ->default('other')
                        ->after('original_name');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sales_order_attachments')) {
            Schema::table('sales_order_attachments', function (Blueprint $table) {
                if (Schema::hasColumn('sales_order_attachments', 'category')) {
                    $table->dropColumn('category');
                }
            });
        }

        if (Schema::hasTable('billing_documents')) {
            Schema::table('billing_documents', function (Blueprint $table) {
                if (Schema::hasColumn('billing_documents', 'so_billing_term_id')) {
                    try {
                        $table->dropIndex('billings_so_term_idx');
                    } catch (\Throwable $e) {
                        // ignore
                    }
                    $table->dropConstrainedForeignId('so_billing_term_id');
                }
            });
        }

        if (Schema::hasTable('purchase_order_lines')) {
            Schema::table('purchase_order_lines', function (Blueprint $table) {
                if (Schema::hasColumn('purchase_order_lines', 'sales_order_line_id')) {
                    try {
                        $table->dropIndex('pol_so_line_idx');
                    } catch (\Throwable $e) {
                        // ignore
                    }
                    $table->dropConstrainedForeignId('sales_order_line_id');
                }
            });
        }

        if (Schema::hasTable('sales_order_lines')) {
            Schema::table('sales_order_lines', function (Blueprint $table) {
                if (Schema::hasColumn('sales_order_lines', 'baseline_line_total')) {
                    $table->dropColumn('baseline_line_total');
                }
                if (Schema::hasColumn('sales_order_lines', 'baseline_unit_price')) {
                    $table->dropColumn('baseline_unit_price');
                }
                if (Schema::hasColumn('sales_order_lines', 'baseline_unit')) {
                    $table->dropColumn('baseline_unit');
                }
                if (Schema::hasColumn('sales_order_lines', 'baseline_qty')) {
                    $table->dropColumn('baseline_qty');
                }
                if (Schema::hasColumn('sales_order_lines', 'baseline_item_variant_id')) {
                    $table->dropConstrainedForeignId('baseline_item_variant_id');
                }
                if (Schema::hasColumn('sales_order_lines', 'baseline_item_id')) {
                    $table->dropConstrainedForeignId('baseline_item_id');
                }
                if (Schema::hasColumn('sales_order_lines', 'baseline_description')) {
                    $table->dropColumn('baseline_description');
                }
                if (Schema::hasColumn('sales_order_lines', 'baseline_name')) {
                    $table->dropColumn('baseline_name');
                }
                if (Schema::hasColumn('sales_order_lines', 'baseline_project_quotation_line_id')) {
                    try {
                        $table->dropIndex('sol_baseline_pql_idx');
                    } catch (\Throwable $e) {
                        // ignore
                    }
                    $table->dropColumn('baseline_project_quotation_line_id');
                }
            });
        }

        if (Schema::hasTable('sales_orders')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                if (Schema::hasColumn('sales_orders', 'customer_ref_type')) {
                    $table->dropColumn('customer_ref_type');
                }
                if (Schema::hasColumn('sales_orders', 'project_quotation_id')) {
                    $table->dropConstrainedForeignId('project_quotation_id');
                }
            });
        }
    }
};

