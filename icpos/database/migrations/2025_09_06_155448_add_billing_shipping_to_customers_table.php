<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Billing
            $table->text('billing_street')->nullable();
            $table->string('billing_city', 128)->nullable();
            $table->string('billing_state', 128)->nullable();
            $table->string('billing_zip', 32)->nullable();
            $table->string('billing_country', 2)->nullable(); // ISO2, ex: ID
            $table->text('billing_notes')->nullable();

            // Shipping
            $table->text('shipping_street')->nullable();
            $table->string('shipping_city', 128)->nullable();
            $table->string('shipping_state', 128)->nullable();
            $table->string('shipping_zip', 32)->nullable();
            $table->string('shipping_country', 2)->nullable();
            $table->text('shipping_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'billing_street','billing_city','billing_state','billing_zip','billing_country','billing_notes',
                'shipping_street','shipping_city','shipping_state','shipping_zip','shipping_country','shipping_notes',
            ]);
        });
    }
};