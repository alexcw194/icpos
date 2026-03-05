<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            if (!Schema::hasColumn('prospects', 'manual_sub_industry')) {
                $table->string('manual_sub_industry', 120)
                    ->nullable()
                    ->after('website');
            }
            if (!Schema::hasColumn('prospects', 'manual_employee_range')) {
                $table->string('manual_employee_range', 40)
                    ->nullable()
                    ->after('manual_sub_industry');
            }
            if (!Schema::hasColumn('prospects', 'manual_linkedin_url')) {
                $table->string('manual_linkedin_url', 255)
                    ->nullable()
                    ->after('manual_employee_range');
            }
        });
    }

    public function down(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $drops = [];
            if (Schema::hasColumn('prospects', 'manual_linkedin_url')) {
                $drops[] = 'manual_linkedin_url';
            }
            if (Schema::hasColumn('prospects', 'manual_employee_range')) {
                $drops[] = 'manual_employee_range';
            }
            if (Schema::hasColumn('prospects', 'manual_sub_industry')) {
                $drops[] = 'manual_sub_industry';
            }

            if ($drops !== []) {
                $table->dropColumn($drops);
            }
        });
    }
};
