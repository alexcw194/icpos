<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('documents')) {
            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            if (!Schema::hasColumn('documents', 'document_date')) {
                $table->date('document_date')->nullable()->after('title');
            }
        });

        DB::table('documents')
            ->whereNull('document_date')
            ->update([
                'document_date' => DB::raw('DATE(created_at)'),
            ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('documents')) {
            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            if (Schema::hasColumn('documents', 'document_date')) {
                $table->dropColumn('document_date');
            }
        });
    }
};

