<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('prospect_analyses', function (Blueprint $table) {
            if (!Schema::hasColumn('prospect_analyses', 'ai_employee_range')) {
                $table->string('ai_employee_range', 40)
                    ->nullable()
                    ->after('ai_business_output');
            }
        });
    }

    public function down(): void
    {
        Schema::table('prospect_analyses', function (Blueprint $table) {
            if (Schema::hasColumn('prospect_analyses', 'ai_employee_range')) {
                $table->dropColumn('ai_employee_range');
            }
        });
    }
};
