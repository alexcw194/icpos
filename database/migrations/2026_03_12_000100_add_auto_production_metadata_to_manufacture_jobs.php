<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('manufacture_jobs')) {
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE manufacture_jobs MODIFY COLUMN job_type ENUM('cut','assembly','fill','bundle','production') NOT NULL DEFAULT 'assembly'");
            }
        }

        Schema::table('manufacture_jobs', function (Blueprint $table) {
            if (!Schema::hasColumn('manufacture_jobs', 'source_type')) {
                $table->string('source_type', 50)->nullable()->after('posted_at');
            }
            if (!Schema::hasColumn('manufacture_jobs', 'source_id')) {
                $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            }
            if (!Schema::hasColumn('manufacture_jobs', 'source_line_id')) {
                $table->foreignId('source_line_id')->nullable()->after('source_id')->constrained('delivery_lines')->nullOnDelete();
            }
            if (!Schema::hasColumn('manufacture_jobs', 'is_auto')) {
                $table->boolean('is_auto')->default(false)->after('source_line_id');
            }
            if (!Schema::hasColumn('manufacture_jobs', 'reversed_at')) {
                $table->timestamp('reversed_at')->nullable()->after('is_auto');
            }
            if (!Schema::hasColumn('manufacture_jobs', 'reversed_by')) {
                $table->foreignId('reversed_by')->nullable()->after('reversed_at')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('manufacture_jobs', 'reversal_notes')) {
                $table->text('reversal_notes')->nullable()->after('reversed_by');
            }

            try {
                $table->index(['source_type', 'source_id'], 'manufacture_jobs_source_idx');
            } catch (\Throwable $e) {
            }
            try {
                $table->index(['is_auto', 'reversed_at'], 'manufacture_jobs_auto_reversed_idx');
            } catch (\Throwable $e) {
            }
        });
    }

    public function down(): void
    {
        Schema::table('manufacture_jobs', function (Blueprint $table) {
            try {
                $table->dropIndex('manufacture_jobs_source_idx');
            } catch (\Throwable $e) {
            }
            try {
                $table->dropIndex('manufacture_jobs_auto_reversed_idx');
            } catch (\Throwable $e) {
            }

            if (Schema::hasColumn('manufacture_jobs', 'reversal_notes')) {
                $table->dropColumn('reversal_notes');
            }
            if (Schema::hasColumn('manufacture_jobs', 'reversed_by')) {
                $table->dropConstrainedForeignId('reversed_by');
            }
            if (Schema::hasColumn('manufacture_jobs', 'reversed_at')) {
                $table->dropColumn('reversed_at');
            }
            if (Schema::hasColumn('manufacture_jobs', 'is_auto')) {
                $table->dropColumn('is_auto');
            }
            if (Schema::hasColumn('manufacture_jobs', 'source_line_id')) {
                $table->dropConstrainedForeignId('source_line_id');
            }
            if (Schema::hasColumn('manufacture_jobs', 'source_id')) {
                $table->dropColumn('source_id');
            }
            if (Schema::hasColumn('manufacture_jobs', 'source_type')) {
                $table->dropColumn('source_type');
            }
        });

        if (Schema::hasTable('manufacture_jobs')) {
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE manufacture_jobs MODIFY COLUMN job_type ENUM('cut','assembly','fill','bundle') NOT NULL DEFAULT 'assembly'");
            }
        }
    }
};
