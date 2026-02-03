<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained('sales_orders')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('status', 20)->default('draft');
            $table->string('mode', 20)->nullable();
            $table->string('pi_number')->nullable();
            $table->unsignedInteger('pi_revision')->default(0);
            $table->timestamp('pi_issued_at')->nullable();
            $table->string('inv_number')->nullable();
            $table->date('invoice_date')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('ar_posted_at')->nullable();
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->string('currency', 10)->default('IDR');
            $table->text('notes')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 255)->nullable();
            $table->unsignedBigInteger('replaced_by_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('replaced_by_id')->references('id')->on('billing_documents')->nullOnDelete();
            $table->index(['sales_order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_documents');
    }
};
