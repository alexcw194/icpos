<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('sales_order_attachments', function (Blueprint $t) {
            // sales_order_id harus nullable untuk draft
            $t->unsignedBigInteger('sales_order_id')->nullable()->change();
            // token draft untuk mengelompokkan file sebelum SO dibuat
            if (!Schema::hasColumn('sales_order_attachments','draft_token')) {
                $t->string('draft_token', 64)->nullable()->index();
            }
        });
    }
    public function down(): void {
        Schema::table('sales_order_attachments', function (Blueprint $t) {
            // optional rollback
            // $t->dropColumn('draft_token');
        });
    }
};