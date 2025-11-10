<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manufacture_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_item_id')->constrained('items')->cascadeOnDelete();
            $table->decimal('qty_produced', 12, 3);
            $table->enum('job_type', ['cut', 'assembly', 'fill', 'bundle'])->default('assembly');
            $table->json('json_components');
            $table->foreignId('produced_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('produced_at')->useCurrent();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manufacture_jobs');
    }
};
