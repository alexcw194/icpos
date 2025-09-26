<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales_order_attachments', function (Blueprint $t) {
            // jadikan nullable utk draft
            $t->unsignedBigInteger('sales_order_id')->nullable()->change();

            // siapkan kolom draft (abaikan kalau sudah ada)
            if (!Schema::hasColumn('sales_order_attachments', 'draft_token')) {
                $t->string('draft_token', 64)->nullable()->index();
            }

            // pastikan kolom disk ada (kalau Anda pakai 'public' di controller)
            if (!Schema::hasColumn('sales_order_attachments', 'disk')) {
                $t->string('disk', 32)->default('public');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_order_attachments', function (Blueprint $t) {
            // optional rollback (sesuaikan kalau diperlukan)
            // $t->unsignedBigInteger('sales_order_id')->nullable(false)->change();
            // $t->dropColumn(['draft_token', 'disk']);
        });
    }
};