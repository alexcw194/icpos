<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('sales_order_attachments')) {
            Schema::create('sales_order_attachments', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('sales_order_id')->nullable()->index();
                $t->string('draft_token', 64)->nullable()->index();
                $t->string('disk', 32)->default('public');
                $t->string('path');
                $t->string('original_name');
                $t->string('mime', 100)->nullable();
                $t->unsignedBigInteger('size')->nullable();
                $t->unsignedBigInteger('uploaded_by')->nullable()->index();
                $t->timestamps();
            });
        } else {
            // Jika tabel sudah ada, pastikan kolom-kolom kunci juga ada
            Schema::table('sales_order_attachments', function (Blueprint $t) {
                if (!Schema::hasColumn('sales_order_attachments','draft_token')) {
                    $t->string('draft_token', 64)->nullable()->index();
                }
                if (!Schema::hasColumn('sales_order_attachments','disk')) {
                    $t->string('disk', 32)->default('public');
                }
                if (!Schema::hasColumn('sales_order_attachments','path')) {
                    $t->string('path');
                }
                if (!Schema::hasColumn('sales_order_attachments','original_name')) {
                    $t->string('original_name');
                }
                if (!Schema::hasColumn('sales_order_attachments','mime')) {
                    $t->string('mime', 100)->nullable();
                }
                if (!Schema::hasColumn('sales_order_attachments','size')) {
                    $t->unsignedBigInteger('size')->nullable();
                }
                if (!Schema::hasColumn('sales_order_attachments','uploaded_by')) {
                    $t->unsignedBigInteger('uploaded_by')->nullable()->index();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_attachments');
    }
};
