<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manufacture_commission_note_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manufacture_commission_note_id')
                ->constrained('manufacture_commission_notes')
                ->cascadeOnDelete();
            $table->string('category', 32);
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->date('month');
            $table->string('source_key', 191)->unique();
            $table->decimal('qty', 18, 4)->default(0);
            $table->decimal('fee_rate', 18, 2)->default(0);
            $table->decimal('fee_amount', 18, 2)->default(0);
            $table->string('item_name_snapshot');
            $table->string('customer_name_snapshot');
            $table->timestamps();

            $table->index(['month', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manufacture_commission_note_lines');
    }
};
