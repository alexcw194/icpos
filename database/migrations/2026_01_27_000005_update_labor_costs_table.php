<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('labor_costs', function (Blueprint $t) {
            if (Schema::hasColumn('labor_costs', 'labor_id')) {
                $t->dropUnique(['labor_id', 'sub_contractor_id']);
            }
        });

        Schema::table('labor_costs', function (Blueprint $t) {
            if (Schema::hasColumn('labor_costs', 'labor_id')) {
                $t->dropForeign(['labor_id']);
                $t->dropColumn('labor_id');
            }
            if (!Schema::hasColumn('labor_costs', 'item_id')) {
                $t->foreignId('item_id')
                    ->after('sub_contractor_id')
                    ->constrained('items')
                    ->cascadeOnDelete();
            }
            if (!Schema::hasColumn('labor_costs', 'context')) {
                $t->enum('context', ['retail', 'project'])
                    ->default('retail')
                    ->after('item_id');
            }
            if (Schema::hasColumn('labor_costs', 'is_active')) {
                $t->dropColumn('is_active');
            }
        });

        Schema::table('labor_costs', function (Blueprint $t) {
            if (!Schema::hasColumn('labor_costs', 'context') || !Schema::hasColumn('labor_costs', 'item_id')) {
                return;
            }
            $t->unique(['sub_contractor_id', 'item_id', 'context']);
        });
    }

    public function down(): void
    {
        Schema::table('labor_costs', function (Blueprint $t) {
            if (Schema::hasColumn('labor_costs', 'context')) {
                $t->dropUnique(['sub_contractor_id', 'item_id', 'context']);
            }
            if (Schema::hasColumn('labor_costs', 'context')) {
                $t->dropColumn('context');
            }
            if (Schema::hasColumn('labor_costs', 'item_id')) {
                $t->dropConstrainedForeignId('item_id');
            }
            if (!Schema::hasColumn('labor_costs', 'labor_id')) {
                $t->foreignId('labor_id')
                    ->nullable()
                    ->constrained('labors')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('labor_costs', 'is_active')) {
                $t->boolean('is_active')->default(true);
            }
            $t->unique(['labor_id', 'sub_contractor_id']);
        });
    }
};
