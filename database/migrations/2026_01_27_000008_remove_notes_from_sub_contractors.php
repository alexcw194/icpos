<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sub_contractors', function (Blueprint $t) {
            if (Schema::hasColumn('sub_contractors', 'notes')) {
                $t->dropColumn('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sub_contractors', function (Blueprint $t) {
            if (!Schema::hasColumn('sub_contractors', 'notes')) {
                $t->text('notes')->nullable();
            }
        });
    }
};
