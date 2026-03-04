<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ld_scan_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_run_id')->constrained('ld_scan_runs')->cascadeOnDelete();
            $table->foreignId('grid_cell_id')->constrained('ld_grid_cells')->cascadeOnDelete();
            $table->foreignId('keyword_id')->constrained('ld_keywords')->cascadeOnDelete();
            $table->unsignedTinyInteger('page_index')->default(1);
            $table->text('request_url')->nullable();
            $table->json('request_payload')->nullable();
            $table->string('response_status', 64)->nullable()->index();
            $table->unsignedInteger('results_count')->default(0);
            $table->unsignedTinyInteger('next_page_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(
                ['scan_run_id', 'grid_cell_id', 'keyword_id', 'page_index'],
                'ld_scan_logs_run_cell_keyword_page_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ld_scan_logs');
    }
};

