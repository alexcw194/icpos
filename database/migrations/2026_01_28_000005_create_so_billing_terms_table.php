<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('so_billing_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('seq')->default(0);
            $table->string('top_code', 16);
            $table->decimal('percent', 6, 2)->default(0);
            $table->string('note', 190)->nullable();
            $table->enum('status', ['planned', 'invoiced', 'paid'])->default('planned');
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['sales_order_id', 'top_code']);
            $table->index(['sales_order_id', 'seq']);
            $table->foreign('top_code')
                ->references('code')
                ->on('term_of_payments')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('so_billing_terms');
    }
};
