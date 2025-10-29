<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->after('price')->constrained('units');
            $table->foreignId('item_group_id')->nullable()->after('unit_id')->constrained('item_groups');
        });

        // isi default untuk data lama
        $pcsId = DB::table('units')->where('code','pcs')->value('id');
        $genId = DB::table('item_groups')->where('code','general')->value('id');

        if ($pcsId) DB::table('items')->whereNull('unit_id')->update(['unit_id' => $pcsId]);
        if ($genId) DB::table('items')->whereNull('item_group_id')->update(['item_group_id' => $genId]);
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_id');
            $table->dropConstrainedForeignId('item_group_id');
        });
    }
};
