<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('goods_receipt_lines', function (Blueprint $t) {
            $t->engine    = 'InnoDB';
            $t->charset   = 'utf8mb4';
            $t->collation = 'utf8mb4_unicode_ci';

            $t->id();

            // FK disusulkan via hardening
            $t->unsignedBigInteger('goods_receipt_id');
            $t->unsignedBigInteger('item_id');
            $t->unsignedBigInteger('item_variant_id')->nullable();

            // Snapshot untuk histori tampilan
            $t->string('item_name_snapshot');
            $t->string('sku_snapshot')->nullable();

            $t->decimal('qty_received', 18, 4);
            $t->string('uom', 16)->nullable();
            $t->decimal('unit_cost', 18, 2)->default(0);
            $t->decimal('line_total', 18, 2)->default(0);

            $t->timestamps();

            $t->index('goods_receipt_id');
            $t->index('item_id');
            $t->index('item_variant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_lines');
    }
};
