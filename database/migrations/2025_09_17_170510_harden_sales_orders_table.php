<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1) Tambah kolom & index di sales_orders
        Schema::table('sales_orders', function (Blueprint $table) {
            // brand snapshot & currency
            if (!Schema::hasColumn('sales_orders', 'brand_snapshot')) {
                $table->json('brand_snapshot')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('sales_orders', 'currency')) {
                $table->char('currency', 3)->default('IDR')->after('brand_snapshot');
            }

            // kolom cancel
            if (!Schema::hasColumn('sales_orders', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('total');
            }
            if (!Schema::hasColumn('sales_orders', 'cancelled_by_user_id')) {
                $table->foreignId('cancelled_by_user_id')
                    ->nullable()
                    ->after('cancelled_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('sales_orders', 'cancel_reason')) {
                $table->text('cancel_reason')->nullable()->after('cancelled_by_user_id');
            }

            // index umum (cek pakai INFORMATION_SCHEMA)
            if (! $this->indexExists('sales_orders', 'sales_orders_status_index')) {
                $table->index('status', 'sales_orders_status_index');
            }
            if (! $this->indexExists('sales_orders', 'sales_orders_order_date_index')) {
                $table->index('order_date', 'sales_orders_order_date_index');
            }

            // unique so_number (skip jika sudah ada unique di kolom tsb)
            if (! $this->hasUniqueOnColumn('sales_orders', 'so_number')) {
                $table->unique('so_number', 'sales_orders_so_number_unique');
            }
        });

        // 2) Extend ENUM status -> tambah 'cancelled' (MySQL)
        if ($this->isMySQL()) {
            DB::statement("
                ALTER TABLE `sales_orders`
                MODIFY COLUMN `status`
                ENUM('open','partial_delivered','delivered','invoiced','closed','cancelled')
                NOT NULL DEFAULT 'open'
            ");
        }
    }

    public function down(): void
    {
        // Balikkan enum (pastikan tak ada nilai 'cancelled' tersisa)
        if ($this->isMySQL()) {
            DB::table('sales_orders')->where('status', 'cancelled')->update(['status' => 'open']);
            DB::statement("
                ALTER TABLE `sales_orders`
                MODIFY COLUMN `status`
                ENUM('open','partial_delivered','delivered','invoiced','closed')
                NOT NULL DEFAULT 'open'
            ");
        }

        Schema::table('sales_orders', function (Blueprint $table) {
            if (Schema::hasColumn('sales_orders', 'cancel_reason')) {
                $table->dropColumn('cancel_reason');
            }
            if (Schema::hasColumn('sales_orders', 'cancelled_by_user_id')) {
                $table->dropConstrainedForeignId('cancelled_by_user_id');
            }
            if (Schema::hasColumn('sales_orders', 'cancelled_at')) {
                $table->dropColumn('cancelled_at');
            }
            if ($this->indexExists('sales_orders', 'sales_orders_so_number_unique')) {
                $table->dropUnique('sales_orders_so_number_unique');
            }
            if ($this->indexExists('sales_orders', 'sales_orders_status_index')) {
                $table->dropIndex('sales_orders_status_index');
            }
            if ($this->indexExists('sales_orders', 'sales_orders_order_date_index')) {
                $table->dropIndex('sales_orders_order_date_index');
            }
            if (Schema::hasColumn('sales_orders', 'currency')) {
                $table->dropColumn('currency');
            }
            if (Schema::hasColumn('sales_orders', 'brand_snapshot')) {
                $table->dropColumn('brand_snapshot');
            }
        });
    }

    private function isMySQL(): bool
    {
        return DB::connection()->getDriverName() === 'mysql';
    }

    // Cek index by name via INFORMATION_SCHEMA
    private function indexExists(string $table, string $index): bool
    {
        $db = DB::getDatabaseName();
        $rows = DB::select("
            SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?
            LIMIT 1
        ", [$db, $table, $index]);
        return !empty($rows);
    }

    // Cek apakah kolom sudah punya unique index
    private function hasUniqueOnColumn(string $table, string $column): bool
    {
        $db = DB::getDatabaseName();
        $rows = DB::select("
            SELECT 1
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
              AND NON_UNIQUE = 0
            LIMIT 1
        ", [$db, $table, $column]);
        return !empty($rows);
    }
};
