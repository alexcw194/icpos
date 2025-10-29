<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('deliveries')) {
      Schema::create('deliveries', function (Blueprint $t) {
        $t->id();
        $t->foreignId('company_id')->constrained()->cascadeOnDelete();
        $t->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
        $t->foreignId('quotation_id')->nullable()->constrained()->nullOnDelete();
        $t->string('number')->index();        // akan DISAMAKAN dengan invoice.number
        $t->date('date');
        $t->string('recipient')->nullable();
        $t->text('address')->nullable();
        $t->text('notes')->nullable();
        $t->json('brand_snapshot')->nullable();
        $t->timestamps();
      });
    }
    try {
      Schema::table('deliveries', fn(Blueprint $t)=>$t->unique(['company_id','number'],'deliveries_company_number_unique'));
    } catch (\Throwable $e) {}
  }
  public function down(): void {}
};
