<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_document_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_document_id')->constrained('billing_documents')->cascadeOnDelete();
            $table->foreignId('sales_order_line_id')->nullable()->constrained('sales_order_lines')->nullOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('unit', 16)->nullable();
            $table->decimal('qty', 18, 4)->default(0);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->string('discount_type', 16)->nullable();
            $table->decimal('discount_value', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('line_subtotal', 18, 2)->default(0);
            $table->decimal('line_total', 18, 2)->default(0);
            $table->timestamps();

            $table->index(['billing_document_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_document_lines');
    }
};
