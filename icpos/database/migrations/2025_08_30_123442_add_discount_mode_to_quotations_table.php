<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            if (!Schema::hasColumn('quotations', 'discount_mode')) {
                // 'total' (default) atau 'per_item'
                $table->enum('discount_mode', ['total', 'per_item'])
                      ->default('total')
                      ->after('customer_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            if (Schema::hasColumn('quotations', 'discount_mode')) {
                $table->dropColumn('discount_mode');
            }
        });
    }
};
