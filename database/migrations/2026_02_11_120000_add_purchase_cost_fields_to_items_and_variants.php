<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('items')) {
            if (!Schema::hasColumn('items', 'default_cost')) {
                Schema::table('items', function (Blueprint $table) {
                    $table->decimal('default_cost', 18, 2)->nullable()->after('price');
                });
            }

            if (!Schema::hasColumn('items', 'last_cost')) {
                Schema::table('items', function (Blueprint $table) {
                    $table->decimal('last_cost', 18, 2)->nullable()->after('default_cost');
                });
            }

            if (!Schema::hasColumn('items', 'last_cost_at')) {
                Schema::table('items', function (Blueprint $table) {
                    $table->timestamp('last_cost_at')->nullable()->after('last_cost');
                });
            }
        }

        if (Schema::hasTable('item_variants') && !Schema::hasColumn('item_variants', 'last_cost_at')) {
            Schema::table('item_variants', function (Blueprint $table) {
                $table->timestamp('last_cost_at')->nullable()->after('last_cost');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('item_variants') && Schema::hasColumn('item_variants', 'last_cost_at')) {
            Schema::table('item_variants', function (Blueprint $table) {
                $table->dropColumn('last_cost_at');
            });
        }

        if (Schema::hasTable('items')) {
            if (Schema::hasColumn('items', 'last_cost_at')) {
                Schema::table('items', function (Blueprint $table) {
                    $table->dropColumn('last_cost_at');
                });
            }

            if (Schema::hasColumn('items', 'last_cost')) {
                Schema::table('items', function (Blueprint $table) {
                    $table->dropColumn('last_cost');
                });
            }

            if (Schema::hasColumn('items', 'default_cost')) {
                Schema::table('items', function (Blueprint $table) {
                    $table->dropColumn('default_cost');
                });
            }
        }
    }
};
