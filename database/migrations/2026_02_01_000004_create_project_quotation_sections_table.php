<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('project_quotation_sections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_quotation_id')
                ->constrained('project_quotations')
                ->cascadeOnDelete();
            $t->string('name');
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_quotation_sections');
    }
};
