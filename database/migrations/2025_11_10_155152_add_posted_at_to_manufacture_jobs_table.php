<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manufacture_jobs', function (Blueprint $table) {
            $table->timestamp('posted_at')->nullable()->after('produced_at');
        });
    }

    public function down(): void
    {
        Schema::table('manufacture_jobs', function (Blueprint $table) {
            $table->dropColumn('posted_at');
        });
    }
};
