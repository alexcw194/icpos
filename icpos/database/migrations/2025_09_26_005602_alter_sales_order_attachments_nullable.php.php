<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales_order_attachments', function (Blueprint $t) {
            // drop FK lama dulu (ganti nama index sesuai di DB-mu kalau berbeda)
            $t->dropForeign(['sales_order_id']);
            // jadikan nullable
            $t->unsignedBigInteger('sales_order_id')->nullable()->change();
            // buat FK baru yang membolehkan null
            $t->foreign('sales_order_id')
              ->references('id')->on('sales_orders')
              ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_order_attachments', function (Blueprint $t) {
            $t->dropForeign(['sales_order_id']);
            $t->unsignedBigInteger('sales_order_id')->nullable(false)->change();
            $t->foreign('sales_order_id')
              ->references('id')->on('sales_orders')
              ->cascadeOnDelete();
        });
    }
};