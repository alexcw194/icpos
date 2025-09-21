<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('companies', function (Blueprint $table) {
            if (!Schema::hasColumn('companies','require_npwp_on_so')) {
                $table->boolean('require_npwp_on_so')
                      ->default(false)
                      ->after('is_taxable'); // sesuaikan kolom tetangga jika perlu
            }
        });
    }
    public function down(): void {
        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies','require_npwp_on_so')) {
                $table->dropColumn('require_npwp_on_so');
            }
        });
    }
};
