<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('banks', function (Blueprint $t) {
            // pastikan kolom dasar ada/ditambahkan jika perlu
            if (!Schema::hasColumn('banks', 'company_id')) {
                $t->unsignedBigInteger('company_id')->nullable()->after('id');
                // optional FK:
                // $t->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
            }
            if (!Schema::hasColumn('banks', 'account_name')) {
                $t->string('account_name', 150)->nullable()->after('name');
            }
            if (!Schema::hasColumn('banks', 'account_no')) {
                $t->string('account_no', 50)->nullable()->after('account_name');
            }

            // yang error
            if (!Schema::hasColumn('banks', 'branch')) {
                $t->string('branch', 100)->nullable()->after('account_no');
            }
            if (!Schema::hasColumn('banks', 'notes')) {
                $t->text('notes')->nullable()->after('branch');
            }
        });
    }

    public function down(): void
    {
        Schema::table('banks', function (Blueprint $t) {
            if (Schema::hasColumn('banks', 'notes'))  $t->dropColumn('notes');
            if (Schema::hasColumn('banks', 'branch')) $t->dropColumn('branch');
            // jangan drop kolom-kolom dasar kalau sudah dipakai produksi
            // $t->dropColumn(['account_no','account_name','company_id']);
        });
    }
};
