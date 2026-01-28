<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->foreignId('project_id')
                ->nullable()
                ->constrained('projects')
                ->nullOnDelete()
                ->after('po_type');
            $table->string('project_name')->nullable()->after('project_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropColumn(['project_id','project_name']);
        });
    }
};
