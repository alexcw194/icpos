<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('bq_csv_conversions')) {
            return;
        }

        Schema::table('bq_csv_conversions', function (Blueprint $table) {
            if (!Schema::hasColumn('bq_csv_conversions', 'target_source_type')) {
                $table->string('target_source_type', 20)->nullable()->after('mapped_item');
            }
            if (!Schema::hasColumn('bq_csv_conversions', 'target_item_id')) {
                $table->foreignId('target_item_id')
                    ->nullable()
                    ->after('target_source_type')
                    ->constrained('items')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('bq_csv_conversions', 'target_item_variant_id')) {
                $table->foreignId('target_item_variant_id')
                    ->nullable()
                    ->after('target_item_id')
                    ->constrained('item_variants')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('bq_csv_conversions')) {
            return;
        }

        Schema::table('bq_csv_conversions', function (Blueprint $table) {
            if (Schema::hasColumn('bq_csv_conversions', 'target_item_variant_id')) {
                $table->dropConstrainedForeignId('target_item_variant_id');
            }
            if (Schema::hasColumn('bq_csv_conversions', 'target_item_id')) {
                $table->dropConstrainedForeignId('target_item_id');
            }
            if (Schema::hasColumn('bq_csv_conversions', 'target_source_type')) {
                $table->dropColumn('target_source_type');
            }
        });
    }
};

