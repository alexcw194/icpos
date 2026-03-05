<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if ($this->isMySql()) {
            DB::statement("
                ALTER TABLE `prospects`
                MODIFY `status` ENUM('new','assigned','rejected','converted','ignored')
                NOT NULL DEFAULT 'new'
            ");
        }

        Schema::table('prospects', function (Blueprint $table) {
            if (!Schema::hasColumn('prospects', 'assigned_at')) {
                $table->timestamp('assigned_at')->nullable()->after('owner_user_id');
            }
            if (!Schema::hasColumn('prospects', 'assigned_by_user_id')) {
                $table->foreignId('assigned_by_user_id')
                    ->nullable()
                    ->after('assigned_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('prospects', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('assigned_by_user_id');
            }
            if (!Schema::hasColumn('prospects', 'rejected_by_user_id')) {
                $table->foreignId('rejected_by_user_id')
                    ->nullable()
                    ->after('rejected_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('prospects', 'reject_reason')) {
                $table->text('reject_reason')->nullable()->after('rejected_by_user_id');
            }
        });

        Schema::table('prospects', function (Blueprint $table) {
            $table->index(['status', 'owner_user_id'], 'prospects_status_owner_idx');
            $table->index(['status', 'assigned_at'], 'prospects_status_assigned_at_idx');
            $table->index(['status', 'rejected_at'], 'prospects_status_rejected_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->dropIndex('prospects_status_owner_idx');
            $table->dropIndex('prospects_status_assigned_at_idx');
            $table->dropIndex('prospects_status_rejected_at_idx');

            if (Schema::hasColumn('prospects', 'reject_reason')) {
                $table->dropColumn('reject_reason');
            }
            if (Schema::hasColumn('prospects', 'rejected_by_user_id')) {
                $table->dropConstrainedForeignId('rejected_by_user_id');
            }
            if (Schema::hasColumn('prospects', 'rejected_at')) {
                $table->dropColumn('rejected_at');
            }
            if (Schema::hasColumn('prospects', 'assigned_by_user_id')) {
                $table->dropConstrainedForeignId('assigned_by_user_id');
            }
            if (Schema::hasColumn('prospects', 'assigned_at')) {
                $table->dropColumn('assigned_at');
            }
        });

        if ($this->isMySql()) {
            DB::statement("
                ALTER TABLE `prospects`
                MODIFY `status` ENUM('new','assigned','converted','ignored')
                NOT NULL DEFAULT 'new'
            ");
        }
    }

    private function isMySql(): bool
    {
        return Schema::getConnection()->getDriverName() === 'mysql';
    }
};
