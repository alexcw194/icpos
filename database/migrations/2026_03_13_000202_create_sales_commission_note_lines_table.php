<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_commission_note_lines')) {
            return;
        }

        Schema::create('sales_commission_note_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_commission_note_id')->constrained('sales_commission_notes')->cascadeOnDelete();
            $table->string('source_key', 191)->unique();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('invoice_line_id')->nullable()->constrained('invoice_lines')->nullOnDelete();
            $table->foreignId('sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();
            $table->foreignId('sales_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('project_scope', 32)->nullable();
            $table->date('month');
            $table->decimal('revenue', 18, 2)->default(0);
            $table->decimal('under_allocated', 18, 2)->default(0);
            $table->decimal('commissionable_base', 18, 2)->default(0);
            $table->decimal('rate_percent', 8, 2)->default(0);
            $table->decimal('fee_amount', 18, 2)->default(0);
            $table->string('invoice_number_snapshot', 100)->nullable();
            $table->string('sales_order_number_snapshot', 100)->nullable();
            $table->string('salesperson_name_snapshot')->nullable();
            $table->string('item_name_snapshot');
            $table->string('customer_name_snapshot');
            $table->timestamps();

            $table->index(['month', 'sales_user_id']);
            $table->index(['sales_order_id', 'sales_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_commission_note_lines');
    }
};
