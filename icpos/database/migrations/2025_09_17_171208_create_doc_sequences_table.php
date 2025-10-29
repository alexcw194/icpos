<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('doc_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('doc_type', 20); // contoh: SO, INV, DN
            $table->integer('year');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['company_id','doc_type','year'], 'docseq_company_type_year_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doc_sequences');
    }
};
