<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration {
public function up(): void
{
Schema::create('invoice_lines', function (Blueprint $table) {
$table->id();
$table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
$table->foreignId('quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
$table->foreignId('quotation_line_id')->nullable()->constrained('quotation_lines')->nullOnDelete();
$table->foreignId('sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();
$table->foreignId('sales_order_line_id')->nullable()->constrained('sales_order_lines')->nullOnDelete();
$table->foreignId('delivery_id')->nullable()->constrained('deliveries')->nullOnDelete();
$table->foreignId('delivery_line_id')->nullable()->constrained('delivery_lines')->nullOnDelete();
$table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
$table->foreignId('item_variant_id')->nullable()->constrained('item_variants')->nullOnDelete();


$table->string('description'); // rendered line desc
$table->string('unit', 16)->default('pcs'); // keep lowercase to align existing
$table->decimal('qty', 18, 4)->default(0);
$table->decimal('unit_price', 18, 2)->default(0);
$table->decimal('discount_amount', 18, 2)->default(0);
$table->decimal('line_subtotal', 18, 2)->default(0); // qty*unit_price before discount
$table->decimal('line_total', 18, 2)->default(0); // after discount


$table->json('snapshot_json')->nullable(); // sku, size, color, notes, etc.
$table->timestamps();
$table->index(['invoice_id']);
});
}


public function down(): void
{
Schema::dropIfExists('invoice_lines');
}
};