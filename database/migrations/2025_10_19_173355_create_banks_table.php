<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('banks', function (Blueprint $t) {
            $t->id();
            $t->string('code', 20)->nullable();         // e.g. BCA, MANDIRI
            $t->string('name', 100);                     // e.g. Bank Central Asia
            $t->string('account_name', 150)->nullable(); // optional
            $t->string('account_no', 80)->nullable();    // optional
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('banks');
    }
};