<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('quotations', function (Blueprint $t) {
      if (!Schema::hasColumn('quotations','company_id'))   $t->foreignId('company_id')->after('id')->constrained()->cascadeOnDelete();
      if (!Schema::hasColumn('quotations','customer_id'))  $t->foreignId('customer_id')->after('company_id')->constrained()->cascadeOnDelete();
      if (!Schema::hasColumn('quotations','number'))       $t->string('number')->index()->after('customer_id');
      if (!Schema::hasColumn('quotations','date'))         $t->date('date')->after('number');
      if (!Schema::hasColumn('quotations','valid_until'))  $t->date('valid_until')->nullable()->after('date');
      if (!Schema::hasColumn('quotations','status'))       $t->string('status',16)->default('draft')->after('valid_until');
      if (!Schema::hasColumn('quotations','notes'))        $t->text('notes')->nullable()->after('status');
      if (!Schema::hasColumn('quotations','terms'))        $t->text('terms')->nullable()->after('notes');
      if (!Schema::hasColumn('quotations','subtotal'))     $t->decimal('subtotal',15,2)->default(0)->after('terms');
      if (!Schema::hasColumn('quotations','discount'))     $t->decimal('discount',15,2)->default(0)->after('subtotal');
      if (!Schema::hasColumn('quotations','tax_percent'))  $t->decimal('tax_percent',5,2)->default(0)->after('discount');
      if (!Schema::hasColumn('quotations','tax_amount'))   $t->decimal('tax_amount',15,2)->default(0)->after('tax_percent');
      if (!Schema::hasColumn('quotations','total'))        $t->decimal('total',15,2)->default(0)->after('tax_amount');
      if (!Schema::hasColumn('quotations','currency'))     $t->string('currency',3)->default('IDR')->after('total');
      if (!Schema::hasColumn('quotations','brand_snapshot')) $t->json('brand_snapshot')->nullable()->after('currency');
    });
    try {
      Schema::table('quotations', fn(Blueprint $t) => $t->unique(['company_id','number'],'quotations_company_number_unique'));
    } catch (\Throwable $e) {}
  }
  public function down(): void {}
};
