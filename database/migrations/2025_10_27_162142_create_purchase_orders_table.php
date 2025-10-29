<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('purchase_orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnUpdate();
            $t->foreignId('supplier_id')->constrained('customers')->cascadeOnUpdate(); // atau 'suppliers' jika sudah ada
            $t->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $t->string('number')->unique();
            $t->date('order_date')->nullable();
            $t->string('status')->default('draft'); // draft|approved|partially_received|fully_received|closed
            $t->decimal('subtotal', 18, 2)->default(0);
            $t->decimal('discount_amount', 18, 2)->default(0);
            $t->decimal('tax_percent', 5, 2)->default(0);
            $t->decimal('tax_amount', 18, 2)->default(0);
            $t->decimal('total', 18, 2)->default(0);
            $t->text('notes')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('purchase_orders'); }
};
