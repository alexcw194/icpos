<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_commission_notes', function (Blueprint $table) {
            $table->id();
            $table->string('number', 50)->unique();
            $table->date('month');
            $table->foreignId('sales_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['unpaid', 'paid'])->default('unpaid');
            $table->date('note_date');
            $table->date('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['month', 'status']);
            $table->index(['sales_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_commission_notes');
    }
};
