<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sales_order_attachments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $t->string('path');
            $t->string('original_name')->nullable();
            $t->string('mime')->nullable();
            $t->unsignedBigInteger('size')->nullable();
            $t->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('sales_order_attachments');
    }
};
