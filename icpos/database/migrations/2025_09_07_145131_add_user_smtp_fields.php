<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users','smtp_username')) {
                $table->string('smtp_username')->nullable()->after('email');
            }
            if (!Schema::hasColumn('users','smtp_password')) {
                // akan dienkripsi oleh cast Eloquent
                $table->text('smtp_password')->nullable()->after('smtp_username');
            }
            if (!Schema::hasColumn('users','email_signature')) {
                $table->text('email_signature')->nullable()->after('smtp_password');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users','email_signature')) $table->dropColumn('email_signature');
            if (Schema::hasColumn('users','smtp_password'))   $table->dropColumn('smtp_password');
            if (Schema::hasColumn('users','smtp_username'))   $table->dropColumn('smtp_username');
        });
    }
};
