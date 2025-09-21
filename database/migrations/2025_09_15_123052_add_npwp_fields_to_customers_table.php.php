<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers','npwp_number')) {
                $table->string('npwp_number', 32)->nullable()->after('address');
            }
            if (!Schema::hasColumn('customers','npwp_name')) {
                $table->string('npwp_name', 255)->nullable()->after('npwp_number');
            }
            if (!Schema::hasColumn('customers','npwp_address')) {
                $table->text('npwp_address')->nullable()->after('npwp_name');
            }
            // Index ringan untuk pencarian
            $table->index('npwp_number', 'customers_npwp_number_idx');
        });
    }
    public function down(): void {
        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers','customers_npwp_number_idx')) {
                $table->dropIndex('customers_npwp_number_idx');
            }
            foreach (['npwp_address','npwp_name','npwp_number'] as $col) {
                if (Schema::hasColumn('customers',$col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
