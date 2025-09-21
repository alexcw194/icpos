<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // sales_order_lines
        Schema::table('sales_order_lines', function (Blueprint $table) {
            // index komposit
            if (! $this->indexExists('sales_order_lines', 'sol_soid_pos_index')) {
                $table->index(['sales_order_id','position'], 'sol_soid_pos_index');
            }
        });

        // Tambah FK lines -> sales_orders jika belum ada
        if (! $this->foreignKeyExists('sales_order_lines', 'sales_order_lines_sales_order_id_foreign')) {
            Schema::table('sales_order_lines', function (Blueprint $table) {
                $table->foreign('sales_order_id')
                    ->references('id')->on('sales_orders')
                    ->cascadeOnDelete();
            });
        }

        // sales_order_attachments
        Schema::table('sales_order_attachments', function (Blueprint $table) {
            if (! $this->indexExists('sales_order_attachments', 'soa_soid_index')) {
                $table->index('sales_order_id', 'soa_soid_index');
            }
        });

        if (! $this->foreignKeyExists('sales_order_attachments', 'sales_order_attachments_sales_order_id_foreign')) {
            Schema::table('sales_order_attachments', function (Blueprint $table) {
                $table->foreign('sales_order_id')
                    ->references('id')->on('sales_orders')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        // lines
        if ($this->foreignKeyExists('sales_order_lines', 'sales_order_lines_sales_order_id_foreign')) {
            Schema::table('sales_order_lines', function (Blueprint $table) {
                $table->dropForeign('sales_order_lines_sales_order_id_foreign');
            });
        }
        Schema::table('sales_order_lines', function (Blueprint $table) {
            if ($this->indexExists('sales_order_lines', 'sol_soid_pos_index')) {
                $table->dropIndex('sol_soid_pos_index');
            }
        });

        // attachments
        if ($this->foreignKeyExists('sales_order_attachments', 'sales_order_attachments_sales_order_id_foreign')) {
            Schema::table('sales_order_attachments', function (Blueprint $table) {
                $table->dropForeign('sales_order_attachments_sales_order_id_foreign');
            });
        }
        Schema::table('sales_order_attachments', function (Blueprint $table) {
            if ($this->indexExists('sales_order_attachments', 'soa_soid_index')) {
                $table->dropIndex('soa_soid_index');
            }
        });
    }

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

    private function foreignKeyExists(string $table, string $fkName): bool
    {
        $db = DB::getDatabaseName();
        $rows = DB::select("
            SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'
            LIMIT 1
        ", [$db, $table, $fkName]);
        return !empty($rows);
    }
};
