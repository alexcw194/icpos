<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $t) {
            if (!Schema::hasColumn('quotations', 'sent_at')) {
                $t->timestamp('sent_at')->nullable()->after('status');
                $t->index('sent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $t) {
            if (Schema::hasColumn('quotations', 'sent_at')) {
                $t->dropIndex(['sent_at']); // atau: $t->dropIndex('quotations_sent_at_index');
                $t->dropColumn('sent_at');
            }
        });
    }
};
