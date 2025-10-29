<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            if (Schema::hasColumn('deliveries', 'number')) {
                $table->string('number')->nullable()->change();
            } else {
                $table->string('number')->nullable();
            }

            if (!Schema::hasColumn('deliveries', 'customer_id')) {
                $table->foreignId('customer_id')->nullable()->after('company_id')->constrained('customers')->nullOnDelete();
            }
            if (!Schema::hasColumn('deliveries', 'warehouse_id')) {
                $table->foreignId('warehouse_id')->nullable()->after('customer_id')->constrained('warehouses')->nullOnDelete();
            }
            if (!Schema::hasColumn('deliveries', 'status')) {
                $table->string('status', 20)->default('draft')->after('number');
            }
            if (!Schema::hasColumn('deliveries', 'reference')) {
                $table->string('reference')->nullable()->after('warehouse_id');
            }
            if (!Schema::hasColumn('deliveries', 'sales_order_id')) {
                $table->foreignId('sales_order_id')->nullable()->after('quotation_id')->constrained('sales_orders')->nullOnDelete();
            }
            if (!Schema::hasColumn('deliveries', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('notes')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('deliveries', 'posted_by')) {
                $table->foreignId('posted_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('deliveries', 'posted_at')) {
                $table->timestamp('posted_at')->nullable()->after('posted_by');
            }
            if (!Schema::hasColumn('deliveries', 'cancelled_by')) {
                $table->foreignId('cancelled_by')->nullable()->after('posted_at')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('deliveries', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
            }
            if (!Schema::hasColumn('deliveries', 'cancel_reason')) {
                $table->text('cancel_reason')->nullable()->after('cancelled_at');
            }
        });

        Schema::create('delivery_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained('deliveries')->cascadeOnDelete();
            $table->foreignId('quotation_line_id')->nullable()->constrained('quotation_lines')->nullOnDelete();
            $table->foreignId('sales_order_line_id')->nullable()->constrained('sales_order_lines')->nullOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->foreignId('item_variant_id')->nullable()->constrained('item_variants')->nullOnDelete();
            $table->string('description');
            $table->string('unit', 50)->nullable();
            $table->decimal('qty', 18, 4);
            $table->decimal('qty_requested', 18, 4)->nullable();
            $table->decimal('price_snapshot', 18, 2)->nullable();
            $table->decimal('qty_backordered', 18, 4)->default(0);
            $table->text('line_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('delivery_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained('deliveries')->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 70)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        if (!Schema::hasColumn('quotation_lines', 'qty_delivered')) {
            Schema::table('quotation_lines', function (Blueprint $table) {
                $table->decimal('qty_delivered', 18, 4)->default(0)->after('qty');
            });
        }

        if (Schema::hasTable('sales_order_lines') && !Schema::hasColumn('sales_order_lines', 'qty_delivered')) {
            Schema::table('sales_order_lines', function (Blueprint $table) {
                $table->decimal('qty_delivered', 18, 4)->default(0)->after('qty_ordered');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sales_order_lines') && Schema::hasColumn('sales_order_lines', 'qty_delivered')) {
            Schema::table('sales_order_lines', function (Blueprint $table) {
                $table->dropColumn('qty_delivered');
            });
        }

        if (Schema::hasColumn('quotation_lines', 'qty_delivered')) {
            Schema::table('quotation_lines', function (Blueprint $table) {
                $table->dropColumn('qty_delivered');
            });
        }

        Schema::dropIfExists('delivery_attachments');
        Schema::dropIfExists('delivery_lines');

        Schema::table('deliveries', function (Blueprint $table) {
            if (Schema::hasColumn('deliveries', 'cancel_reason')) {
                $table->dropColumn('cancel_reason');
            }
            if (Schema::hasColumn('deliveries', 'cancelled_at')) {
                $table->dropColumn('cancelled_at');
            }
            if (Schema::hasColumn('deliveries', 'cancelled_by')) {
                $table->dropConstrainedForeignId('cancelled_by');
            }
            if (Schema::hasColumn('deliveries', 'posted_at')) {
                $table->dropColumn('posted_at');
            }
            if (Schema::hasColumn('deliveries', 'posted_by')) {
                $table->dropConstrainedForeignId('posted_by');
            }
            if (Schema::hasColumn('deliveries', 'created_by')) {
                $table->dropConstrainedForeignId('created_by');
            }
            if (Schema::hasColumn('deliveries', 'reference')) {
                $table->dropColumn('reference');
            }
            if (Schema::hasColumn('deliveries', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('deliveries', 'warehouse_id')) {
                $table->dropConstrainedForeignId('warehouse_id');
            }
            if (Schema::hasColumn('deliveries', 'customer_id')) {
                $table->dropConstrainedForeignId('customer_id');
            }
            if (Schema::hasColumn('deliveries', 'sales_order_id')) {
                $table->dropConstrainedForeignId('sales_order_id');
            }
        });
    }
};
