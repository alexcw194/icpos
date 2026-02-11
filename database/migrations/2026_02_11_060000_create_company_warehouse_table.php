<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('company_warehouse')) {
            Schema::create('company_warehouse', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['company_id', 'warehouse_id'], 'company_warehouse_unique');
                $table->index(['warehouse_id', 'company_id'], 'company_warehouse_reverse_idx');
            });
        }

        if (!Schema::hasTable('warehouses') || !Schema::hasColumn('warehouses', 'company_id')) {
            return;
        }

        DB::table('warehouses')
            ->select(['id', 'company_id', 'created_at', 'updated_at'])
            ->whereNotNull('company_id')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                $payload = [];

                foreach ($rows as $row) {
                    if (!$row->company_id) {
                        continue;
                    }
                    $payload[] = [
                        'company_id' => (int) $row->company_id,
                        'warehouse_id' => (int) $row->id,
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => $row->updated_at ?? now(),
                    ];
                }

                if (!empty($payload)) {
                    DB::table('company_warehouse')->upsert(
                        $payload,
                        ['company_id', 'warehouse_id'],
                        ['updated_at']
                    );
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_warehouse');
    }
};

