<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $t) {
            $t->string('name_key', 190)->nullable()->index();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->index();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $t) {
            $t->dropConstrainedForeignId('created_by');
            $t->dropColumn(['name_key','created_by']);
        });
    }
};
