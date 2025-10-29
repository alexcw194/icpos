<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // <- perhatikan backslash sebelum DB

return new class extends Migration {
    public function up(): void
    {
        Schema::create('item_groups', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->timestamps();
        });

        DB::table('item_groups')->insertOrIgnore([
            ['code' => 'general', 'name' => 'General', 'created_at'=>now(), 'updated_at'=>now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('item_groups');
    }
};
