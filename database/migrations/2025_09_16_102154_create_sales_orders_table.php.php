<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sales_orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $t->foreignId('customer_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $t->foreignId('quotation_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('sales_user_id')->nullable()->constrained('users')->nullOnDelete();

            $t->string('so_number')->unique();
            $t->date('order_date');

            $t->string('customer_po_number');
            $t->date('customer_po_date');
            $t->date('deadline')->nullable();

            $t->text('ship_to')->nullable();
            $t->text('bill_to')->nullable();
            $t->text('notes')->nullable();

            $t->enum('discount_mode', ['total','per_item'])->default('total');

            $t->decimal('lines_subtotal', 18, 2)->default(0);
            $t->enum('total_discount_type', ['amount','percent'])->default('amount');
            $t->decimal('total_discount_value', 18, 2)->default(0);
            $t->decimal('total_discount_amount', 18, 2)->default(0);

            $t->decimal('taxable_base', 18, 2)->default(0);
            $t->decimal('tax_percent', 5, 2)->default(0);
            $t->decimal('tax_amount', 18, 2)->default(0);
            $t->decimal('total', 18, 2)->default(0);

            // NPWP policy (snapshot + flag)
            $t->boolean('npwp_required')->default(false);
            $t->enum('npwp_status', ['ok','missing'])->default('missing');
            $t->string('tax_npwp_number')->nullable();
            $t->string('tax_npwp_name')->nullable();
            $t->text('tax_npwp_address')->nullable();

            $t->enum('status', ['open','partial_delivered','delivered','invoiced','closed'])->default('open');

            $t->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('sales_orders');
    }
};
