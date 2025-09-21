<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        // Sesuaikan alias/nama sesuai datamu
        DB::table('companies')->where('alias','ICP')->update(['require_npwp_on_so' => true]);
        DB::table('companies')->where('alias','AMP')->update(['require_npwp_on_so' => false]);
    }
    public function down(): void {
        // No-op (biarkan seperti terakhir)
    }
};
