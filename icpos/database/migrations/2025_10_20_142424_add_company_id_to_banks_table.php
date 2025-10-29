<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('banks', function (Blueprint $table) {
      if (!Schema::hasColumn('banks','company_id')) {
        $table->foreignId('company_id')->nullable()->after('id')
              ->constrained()->nullOnDelete();
        $table->index(['company_id','is_active']);
      }
    });
  }
  public function down(): void {
    Schema::table('banks', function (Blueprint $table) {
      if (Schema::hasColumn('banks','company_id')) {
        $table->dropConstrainedForeignId('company_id');
      }
    });
  }
};