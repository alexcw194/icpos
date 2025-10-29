<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $t) {
            $t->engine    = 'InnoDB';
            $t->charset   = 'utf8mb4';
            $t->collation = 'utf8mb4_unicode_ci';

            $t->id();

            // FK ditambahkan via migration hardening agar error lebih diagnosable
            $t->unsignedBigInteger('company_id');
            $t->unsignedBigInteger('warehouse_id')->nullable();
            $t->unsignedBigInteger('purchase_order_id')->nullable();

            // Panjang 128 untuk aman pada UNIQUE utf8mb4
            $t->string('number', 128);        // UNIQUE ditambahkan via hardening
            $t->date('gr_date')->nullable();
            $t->string('status', 32)->default('draft'); // draft|posted
            $t->text('notes')->nullable();
            $t->timestamp('posted_at')->nullable();
            $t->unsignedBigInteger('posted_by')->nullable();

            $t->timestamps();

            // Index dasar (tanpa FK/UNIQUE dulu)
            $t->index('company_id');
            $t->index('warehouse_id');
            $t->index('purchase_order_id');
            $t->index('posted_by');
            $t->index('number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipts');
    }
};
