<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('item_labor_rates', function (Blueprint $t) {
            if (!Schema::hasColumn('item_labor_rates', 'item_variant_id')) {
                $t->foreignId('item_variant_id')
                    ->nullable()
                    ->after('item_id')
                    ->constrained('item_variants')
                    ->nullOnDelete();
            }
            if (Schema::hasColumn('item_labor_rates', 'item_id')) {
                try {
                    $t->dropUnique(['item_id']);
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        });

        Schema::table('item_labor_rates', function (Blueprint $t) {
            if (Schema::hasColumn('item_labor_rates', 'item_variant_id')) {
                $t->unique(['item_id', 'item_variant_id']);
            }
        });

        Schema::table('project_item_labor_rates', function (Blueprint $t) {
            if (!Schema::hasColumn('project_item_labor_rates', 'item_variant_id')) {
                $t->foreignId('item_variant_id')
                    ->nullable()
                    ->after('project_item_id')
                    ->constrained('item_variants')
                    ->nullOnDelete();
            }
            if (Schema::hasColumn('project_item_labor_rates', 'project_item_id')) {
                try {
                    $t->dropUnique(['project_item_id']);
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        });

        Schema::table('project_item_labor_rates', function (Blueprint $t) {
            if (Schema::hasColumn('project_item_labor_rates', 'item_variant_id')) {
                $t->unique(['project_item_id', 'item_variant_id']);
            }
        });

        Schema::table('labor_costs', function (Blueprint $t) {
            if (!Schema::hasColumn('labor_costs', 'item_variant_id')) {
                $t->foreignId('item_variant_id')
                    ->nullable()
                    ->after('item_id')
                    ->constrained('item_variants')
                    ->nullOnDelete();
            }
            try {
                $t->dropUnique(['sub_contractor_id', 'item_id', 'context']);
            } catch (\Throwable $e) {
                // ignore
            }
        });

        Schema::table('labor_costs', function (Blueprint $t) {
            if (Schema::hasColumn('labor_costs', 'item_variant_id')) {
                $t->unique(['sub_contractor_id', 'item_id', 'item_variant_id', 'context']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('labor_costs', function (Blueprint $t) {
            try {
                $t->dropUnique(['sub_contractor_id', 'item_id', 'item_variant_id', 'context']);
            } catch (\Throwable $e) {
                // ignore
            }
            if (Schema::hasColumn('labor_costs', 'item_variant_id')) {
                $t->dropConstrainedForeignId('item_variant_id');
            }
            try {
                $t->unique(['sub_contractor_id', 'item_id', 'context']);
            } catch (\Throwable $e) {
                // ignore
            }
        });

        Schema::table('project_item_labor_rates', function (Blueprint $t) {
            try {
                $t->dropUnique(['project_item_id', 'item_variant_id']);
            } catch (\Throwable $e) {
                // ignore
            }
            if (Schema::hasColumn('project_item_labor_rates', 'item_variant_id')) {
                $t->dropConstrainedForeignId('item_variant_id');
            }
            try {
                $t->unique(['project_item_id']);
            } catch (\Throwable $e) {
                // ignore
            }
        });

        Schema::table('item_labor_rates', function (Blueprint $t) {
            try {
                $t->dropUnique(['item_id', 'item_variant_id']);
            } catch (\Throwable $e) {
                // ignore
            }
            if (Schema::hasColumn('item_labor_rates', 'item_variant_id')) {
                $t->dropConstrainedForeignId('item_variant_id');
            }
            try {
                $t->unique(['item_id']);
            } catch (\Throwable $e) {
                // ignore
            }
        });
    }
};
