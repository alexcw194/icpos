<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('document_counters')) {
      Schema::create('document_counters', function (Blueprint $t) {
        $t->id();
        $t->foreignId('company_id')->constrained()->cascadeOnDelete();
        $t->string('doc_type',16);   // quotation|invoice|delivery
        $t->smallInteger('year');
        $t->unsignedInteger('last_seq')->default(0);
        $t->timestamps();
        $t->unique(['company_id','doc_type','year'],'doc_counters_unique');
      });
    }
  }
  public function down(): void {}
};