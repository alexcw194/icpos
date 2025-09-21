<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Default masa berlaku quotation (hari). Null = pakai fallback 30 di controller.
            if (!Schema::hasColumn('companies', 'default_valid_days')) {
                $table->unsignedSmallInteger('default_valid_days')->nullable()->comment('Default quotation validity (days)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'default_valid_days')) {
                $table->dropColumn('default_valid_days');
            }
        });
    }
};
