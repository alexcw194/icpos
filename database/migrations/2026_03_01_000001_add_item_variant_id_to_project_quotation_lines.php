<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('project_quotation_lines', function (Blueprint $t) {
            if (!Schema::hasColumn('project_quotation_lines', 'item_variant_id')) {
                $t->foreignId('item_variant_id')
                    ->nullable()
                    ->after('item_id')
                    ->constrained('item_variants')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('project_quotation_lines', function (Blueprint $t) {
            if (Schema::hasColumn('project_quotation_lines', 'item_variant_id')) {
                $t->dropConstrainedForeignId('item_variant_id');
            }
        });
    }
};
