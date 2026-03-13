<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('manufacture_commission_notes')) {
            return;
        }

        Schema::create('manufacture_commission_notes', function (Blueprint $table) {
            $table->id();
            $table->string('number', 50)->unique();
            $table->date('month');
            $table->enum('status', ['unpaid', 'paid'])->default('unpaid');
            $table->date('note_date');
            $table->date('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['month', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manufacture_commission_notes');
    }
};
