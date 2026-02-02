<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('purchase_order_terms', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('seq')->default(1);
            $t->string('top_code', 64);
            $t->decimal('percent', 9, 4)->default(0);
            $t->string('note', 190)->nullable();
            $t->string('due_trigger', 32)->nullable();
            $t->unsignedInteger('offset_days')->nullable();
            $t->unsignedTinyInteger('day_of_month')->nullable();
            $t->string('status', 32)->default('planned');
            $t->timestamps();

            $t->index(['purchase_order_id', 'seq']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_terms');
    }
};
