<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // tambahkan hanya jika belum ada
            if (!Schema::hasColumn('customers', 'billing_terms_days')) {
                $table->integer('billing_terms_days')->nullable()->after('phone');
            }
            if (!Schema::hasColumn('customers', 'website')) {
                $table->string('website', 255)->nullable()->after('email');
            }
            if (!Schema::hasColumn('customers', 'notes')) {
                $table->text('notes')->nullable()->after('country');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'billing_terms_days')) {
                $table->dropColumn('billing_terms_days');
            }
            if (Schema::hasColumn('customers', 'website')) {
                $table->dropColumn('website');
            }
            if (Schema::hasColumn('customers', 'notes')) {
                $table->dropColumn('notes');
            }
        });
    }
};
