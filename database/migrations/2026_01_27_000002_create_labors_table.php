<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('labors', function (Blueprint $t) {
            $t->id();
            $t->string('code', 32)->unique();
            $t->string('name', 190);
            $t->string('unit', 20)->default('LS');
            $t->boolean('is_active')->default(true);
            $t->foreignId('default_sub_contractor_id')
                ->nullable()
                ->constrained('sub_contractors')
                ->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('labors');
    }
};
