<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('sizes', function (Blueprint $t) {
            if (!Schema::hasColumn('sizes','sort_order')) {
                $t->integer('sort_order')->default(0)->after('slug');
            }
        });
        Schema::table('colors', function (Blueprint $t) {
            if (!Schema::hasColumn('colors','sort_order')) {
                $t->integer('sort_order')->default(0)->after('slug');
            }
        });
    }
    public function down(): void {
        Schema::table('sizes', function (Blueprint $t) {
            if (Schema::hasColumn('sizes','sort_order')) $t->dropColumn('sort_order');
        });
        Schema::table('colors', function (Blueprint $t) {
            if (Schema::hasColumn('colors','sort_order')) $t->dropColumn('sort_order');
        });
    }
};