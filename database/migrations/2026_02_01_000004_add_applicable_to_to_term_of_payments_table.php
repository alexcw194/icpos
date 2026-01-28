<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('term_of_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('term_of_payments', 'applicable_to')) {
                $table->json('applicable_to')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('term_of_payments', function (Blueprint $table) {
            if (Schema::hasColumn('term_of_payments', 'applicable_to')) {
                $table->dropColumn('applicable_to');
            }
        });
    }
};
