<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('items')) {
            Schema::create('items', function (Blueprint $t) {
                $t->id();                       // unsigned BIGINT
                $t->string('sku')->unique();    // boleh disesuaikan
                $t->string('name');             // minimal kolom dasar
                $t->text('description')->nullable();
                $t->decimal('price', 15, 2)->default(0);
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
