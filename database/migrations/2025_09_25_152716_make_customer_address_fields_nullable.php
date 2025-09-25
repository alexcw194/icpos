<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // alamat utama
            $table->string('address')->nullable()->change();
            $table->string('city', 100)->nullable()->change();
            $table->string('province', 100)->nullable()->change();
            $table->string('country', 100)->nullable()->change();
            $table->string('website')->nullable()->change();
            $table->unsignedSmallInteger('billing_terms_days')->nullable()->change();
            $table->text('notes')->nullable()->change();

            // billing
            $table->string('billing_street')->nullable()->change();
            $table->string('billing_city', 100)->nullable()->change();
            $table->string('billing_state', 100)->nullable()->change();
            $table->string('billing_zip', 20)->nullable()->change();
            $table->string('billing_country', 100)->nullable()->change();
            $table->text('billing_notes')->nullable()->change();

            // shipping
            $table->string('shipping_street')->nullable()->change();
            $table->string('shipping_city', 100)->nullable()->change();
            $table->string('shipping_state', 100)->nullable()->change();
            $table->string('shipping_zip', 20)->nullable()->change();
            $table->string('shipping_country', 100)->nullable()->change();
            $table->text('shipping_notes')->nullable()->change();
        });
    }

    public function down(): void
    {
        // tidak perlu revert ke NOT NULL
    }
};
