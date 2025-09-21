<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users','is_active')) $table->boolean('is_active')->default(true)->after('email');
            if (!Schema::hasColumn('users','last_login_at')) $table->timestamp('last_login_at')->nullable()->after('remember_token');
            if (!Schema::hasColumn('users','profile_image_path')) $table->string('profile_image_path')->nullable()->after('last_login_at');
            if (!Schema::hasColumn('users','email_signature')) $table->text('email_signature')->nullable()->after('profile_image_path');
            if (!Schema::hasColumn('users','must_change_password')) $table->boolean('must_change_password')->default(false)->after('email_signature');
            if (!Schema::hasColumn('users','password_changed_at')) $table->timestamp('password_changed_at')->nullable()->after('must_change_password');
        });
    }
    public function down(): void {
        Schema::table('users', function (Blueprint $table) {
            foreach (['password_changed_at','must_change_password','email_signature','profile_image_path','last_login_at','is_active'] as $c) {
                if (Schema::hasColumn('users',$c)) $table->dropColumn($c);
            }
        });
    }
};
