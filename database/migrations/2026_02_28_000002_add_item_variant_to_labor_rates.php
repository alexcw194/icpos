<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $schema = DB::getDatabaseName();
        $dropIndexIfExists = function (string $table, string $index) use ($schema) {
            $row = DB::selectOne(
                'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
                [$schema, $table, $index]
            );
            if ($row) {
                try {
                    DB::statement("ALTER TABLE `$table` DROP INDEX `$index`");
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        };
        $addUniqueIfMissing = function (string $table, array $columns, string $name) use ($schema) {
            $row = DB::selectOne(
                'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
                [$schema, $table, $name]
            );
            if (!$row) {
                $cols = implode('`,`', $columns);
                try {
                    DB::statement("ALTER TABLE `$table` ADD UNIQUE `$name`(`$cols`)");
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        };
        $dropForeignByColumn = function (string $table, string $column) use ($schema) {
            $row = DB::selectOne(
                'SELECT constraint_name FROM information_schema.key_column_usage WHERE table_schema = ? AND table_name = ? AND column_name = ? AND referenced_table_name IS NOT NULL LIMIT 1',
                [$schema, $table, $column]
            );
            if ($row && !empty($row->constraint_name)) {
                DB::statement("ALTER TABLE `$table` DROP FOREIGN KEY `{$row->constraint_name}`");
            }
        };
        $addUniqueIfMissing = function (string $table, array $columns, string $name) use ($schema) {
            $row = DB::selectOne(
                'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
                [$schema, $table, $name]
            );
            if (!$row) {
                $cols = implode('`,`', $columns);
                try {
                    DB::statement("ALTER TABLE `$table` ADD UNIQUE `$name`(`$cols`)");
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        };

        $dropForeignByColumn('item_labor_rates', 'item_id');
        $dropForeignByColumn('project_item_labor_rates', 'project_item_id');
        $dropIndexIfExists('item_labor_rates', 'item_labor_rates_item_id_unique');
        $dropIndexIfExists('item_labor_rates', 'item_labor_rates_item_id_item_variant_id_unique');
        $dropIndexIfExists('project_item_labor_rates', 'project_item_labor_rates_project_item_id_unique');
        $dropIndexIfExists('project_item_labor_rates', 'project_item_labor_rates_project_item_id_item_variant_id_unique');
        $dropIndexIfExists('labor_costs', 'labor_costs_sub_contractor_id_item_id_context_unique');
        $dropIndexIfExists('labor_costs', 'labor_costs_sub_contractor_id_item_id_item_variant_id_context_unique');

        Schema::table('item_labor_rates', function (Blueprint $t) {
            if (!Schema::hasColumn('item_labor_rates', 'item_variant_id')) {
                $t->foreignId('item_variant_id')
                    ->nullable()
                    ->after('item_id')
                    ->constrained('item_variants')
                    ->nullOnDelete();
            }
        });

        Schema::table('item_labor_rates', function (Blueprint $t) {
            if (Schema::hasColumn('item_labor_rates', 'item_variant_id')) {
                $t->foreign('item_id')->references('id')->on('items')->cascadeOnDelete();
            }
        });
        try {
            $addUniqueIfMissing('item_labor_rates', ['item_id', 'item_variant_id'], 'item_labor_rates_item_id_item_variant_id_unique');
        } catch (\Throwable $e) {
            // ignore
        }

        Schema::table('project_item_labor_rates', function (Blueprint $t) {
            if (!Schema::hasColumn('project_item_labor_rates', 'item_variant_id')) {
                $t->foreignId('item_variant_id')
                    ->nullable()
                    ->after('project_item_id')
                    ->constrained('item_variants')
                    ->nullOnDelete();
            }
        });

        Schema::table('project_item_labor_rates', function (Blueprint $t) {
            if (Schema::hasColumn('project_item_labor_rates', 'item_variant_id')) {
                $t->foreign('project_item_id')->references('id')->on('items')->cascadeOnDelete();
            }
        });
        try {
            $addUniqueIfMissing('project_item_labor_rates', ['project_item_id', 'item_variant_id'], 'project_item_labor_rates_project_item_id_item_variant_id_unique');
        } catch (\Throwable $e) {
            // ignore
        }

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
                // unique added below (guarded)
            }
        });
        try {
            $addUniqueIfMissing('labor_costs', ['sub_contractor_id', 'item_id', 'item_variant_id', 'context'], 'labor_costs_sub_contractor_id_item_id_item_variant_id_context_unique');
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public function down(): void
    {
        $schema = DB::getDatabaseName();
        $dropIndexIfExists = function (string $table, string $index) use ($schema) {
            $row = DB::selectOne(
                'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
                [$schema, $table, $index]
            );
            if ($row) {
                try {
                    DB::statement("ALTER TABLE `$table` DROP INDEX `$index`");
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        };
        $dropForeignByColumn = function (string $table, string $column) use ($schema) {
            $row = DB::selectOne(
                'SELECT constraint_name FROM information_schema.key_column_usage WHERE table_schema = ? AND table_name = ? AND column_name = ? AND referenced_table_name IS NOT NULL LIMIT 1',
                [$schema, $table, $column]
            );
            if ($row && !empty($row->constraint_name)) {
                DB::statement("ALTER TABLE `$table` DROP FOREIGN KEY `{$row->constraint_name}`");
            }
        };

        $dropIndexIfExists('labor_costs', 'labor_costs_sub_contractor_id_item_id_item_variant_id_context_unique');
        $dropIndexIfExists('project_item_labor_rates', 'project_item_labor_rates_project_item_id_item_variant_id_unique');
        $dropIndexIfExists('item_labor_rates', 'item_labor_rates_item_id_item_variant_id_unique');
        $dropForeignByColumn('project_item_labor_rates', 'project_item_id');
        $dropForeignByColumn('item_labor_rates', 'item_id');

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
        try {
            $addUniqueIfMissing('labor_costs', ['sub_contractor_id', 'item_id', 'context'], 'labor_costs_sub_contractor_id_item_id_context_unique');
        } catch (\Throwable $e) {
            // ignore
        }

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
        try {
            $addUniqueIfMissing('project_item_labor_rates', ['project_item_id'], 'project_item_labor_rates_project_item_id_unique');
        } catch (\Throwable $e) {
            // ignore
        }

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
        try {
            $addUniqueIfMissing('item_labor_rates', ['item_id'], 'item_labor_rates_item_id_unique');
        } catch (\Throwable $e) {
            // ignore
        }
    }
};
