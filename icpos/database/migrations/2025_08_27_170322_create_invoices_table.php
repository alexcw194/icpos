<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('invoices')) {
      Schema::create('invoices', function (Blueprint $t) {
        $t->id();
        $t->foreignId('company_id')->constrained()->cascadeOnDelete();
        $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
        $t->foreignId('quotation_id')->nullable()->constrained()->nullOnDelete();
        $t->string('number')->index();
        $t->date('date');
        $t->string('status',16)->default('draft');
        $t->decimal('subtotal',15,2)->default(0);
        $t->decimal('discount',15,2)->default(0);
        $t->decimal('tax_percent',5,2)->default(0);
        $t->decimal('tax_amount',15,2)->default(0);
        $t->decimal('total',15,2)->default(0);
        $t->string('currency',3)->default('IDR');
        $t->json('brand_snapshot')->nullable();
        $t->timestamps();
      });
    }
    try {
      Schema::table('invoices', fn(Blueprint $t)=>$t->unique(['company_id','number'],'invoices_company_number_unique'));
    } catch (\Throwable $e) {}
  }
  public function down(): void {}
};
