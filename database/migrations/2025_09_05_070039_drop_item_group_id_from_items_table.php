<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (Schema::hasColumn('items','item_group_id')) {
            Schema::table('items', function (Blueprint $table) {
                $table->dropConstrainedForeignId('item_group_id'); // drop FK + column
            });
        }
    }
    public function down(): void {
        if (!Schema::hasColumn('items','item_group_id')) {
            Schema::table('items', function (Blueprint $table) {
                $table->foreignId('item_group_id')->nullable()->constrained('item_groups');
            });
        }
    }
};
