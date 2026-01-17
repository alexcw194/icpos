<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('project_quotation_lines', function (Blueprint $t) {
            $t->string('source_type', 16)->default('item')->after('description');
            $t->foreignId('item_id')->nullable()->after('source_type')->constrained('items')->nullOnDelete();
            $t->string('item_label', 255)->nullable()->after('item_id');
            $t->string('labor_source', 20)->default('manual')->after('labor_total');
            $t->decimal('labor_unit_cost_snapshot', 18, 2)->default(0)->after('labor_source');
            $t->string('labor_override_reason', 255)->nullable()->after('labor_unit_cost_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('project_quotation_lines', function (Blueprint $t) {
            $t->dropConstrainedForeignId('item_id');
            $t->dropColumn(['source_type', 'item_label', 'labor_source', 'labor_unit_cost_snapshot', 'labor_override_reason']);
        });
    }
};
