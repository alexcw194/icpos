<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('customers')) return;

        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'jenis_id')) {
                $table->foreignId('jenis_id')
                    ->nullable() // sementara supaya aman ke data lama
                    ->after('notes')
                    ->constrained('jenis')
                    ->restrictOnDelete()
                    ->cascadeOnUpdate();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('customers')) return;

        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'jenis_id')) {
                $table->dropConstrainedForeignId('jenis_id');
            }
        });
    }
};
