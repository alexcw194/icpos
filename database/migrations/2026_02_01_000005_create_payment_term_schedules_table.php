<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_term_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_term_id')
                ->constrained('term_of_payments')
                ->cascadeOnDelete();
            $table->unsignedInteger('sequence')->default(0);
            $table->enum('portion_type', ['percent', 'fixed']);
            $table->decimal('portion_value', 18, 4)->default(0);
            $table->enum('due_trigger', [
                'on_so',
                'on_delivery',
                'on_invoice',
                'after_invoice_days',
                'end_of_month',
            ]);
            $table->unsignedInteger('offset_days')->nullable();
            $table->unsignedInteger('specific_day')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['payment_term_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_term_schedules');
    }
};
