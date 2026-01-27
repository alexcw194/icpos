<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('project_quotations', function (Blueprint $t) {
            if (!Schema::hasColumn('project_quotations', 'sub_contractor_id')) {
                $t->foreignId('sub_contractor_id')
                    ->nullable()
                    ->after('customer_id')
                    ->constrained('sub_contractors')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('project_quotations', function (Blueprint $t) {
            if (Schema::hasColumn('project_quotations', 'sub_contractor_id')) {
                $t->dropConstrainedForeignId('sub_contractor_id');
            }
        });
    }
};
