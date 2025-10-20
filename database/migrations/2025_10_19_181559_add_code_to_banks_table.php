<?php


use Illuminate\Database\Migrations\Migration;   // <- Wajib
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            if (!Schema::hasColumn('banks', 'code')) {
                $table->string('code', 30)->nullable()->index()->after('id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            if (Schema::hasColumn('banks', 'code')) {
                $table->dropColumn('code');
            }
        });
    }
};