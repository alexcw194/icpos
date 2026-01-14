<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('project_quotation_payment_terms', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_quotation_id')
                ->constrained('project_quotations')
                ->cascadeOnDelete();
            $t->string('code', 16);
            $t->string('label')->default('');
            $t->decimal('percent', 6, 2)->default(0);
            $t->unsignedInteger('sequence')->default(0);
            $t->string('trigger_note')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_quotation_payment_terms');
    }
};
