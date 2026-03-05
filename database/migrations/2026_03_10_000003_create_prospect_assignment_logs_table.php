<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('prospect_assignment_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospect_id')->constrained('prospects')->cascadeOnDelete();
            $table->enum('action', ['assigned', 'reassigned', 'rejected', 'converted']);
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('acted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['prospect_id', 'created_at'], 'prospect_assignment_logs_prospect_created_idx');
            $table->index(['action', 'created_at'], 'prospect_assignment_logs_action_created_idx');
            $table->index(['to_user_id', 'created_at'], 'prospect_assignment_logs_to_user_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospect_assignment_logs');
    }
};
