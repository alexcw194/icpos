<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('term_of_payments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('term_of_payments')->insertOrIgnore([
            ['code' => 'DP', 'description' => 'Down Payment', 'is_active' => true],
            ['code' => 'T1', 'description' => 'Termin 1', 'is_active' => true],
            ['code' => 'T2', 'description' => 'Termin 2', 'is_active' => true],
            ['code' => 'T3', 'description' => 'Termin 3', 'is_active' => true],
            ['code' => 'T4', 'description' => 'Termin 4', 'is_active' => true],
            ['code' => 'T5', 'description' => 'Termin 5', 'is_active' => true],
            ['code' => 'FINISH', 'description' => 'Finish', 'is_active' => true],
            ['code' => 'R1', 'description' => 'Retention 1', 'is_active' => true],
            ['code' => 'R2', 'description' => 'Retention 2', 'is_active' => true],
            ['code' => 'R3', 'description' => 'Retention 3', 'is_active' => true],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('term_of_payments');
    }
};
