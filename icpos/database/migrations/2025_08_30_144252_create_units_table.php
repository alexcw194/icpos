<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique(); // pcs, box, m, dll.
            $table->string('name', 50);
            $table->timestamps();
        });

        // seed minimal
        DB::table('units')->insertOrIgnore([
            ['code' => 'pcs', 'name' => 'Pieces', 'created_at'=>now(), 'updated_at'=>now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
