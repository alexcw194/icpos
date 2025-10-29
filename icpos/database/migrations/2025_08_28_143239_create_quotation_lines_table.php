<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Jika tabel belum ada, kita buat lengkap sekalian kolom diskon
        if (!Schema::hasTable('quotation_lines')) {
            Schema::create('quotation_lines', function (Blueprint $t) {
                $t->id();

                // Pakai unsignedBigInteger agar tidak gagal kalau tabel quotations belum ada/beda struktur
                $t->unsignedBigInteger('quotation_id')->index();

                $t->string('name', 200);
                $t->text('description')->nullable();
                $t->string('unit', 50)->nullable();

                $t->decimal('qty', 15, 4)->default(0);
                $t->decimal('unit_price', 15, 2)->default(0);

                // Kolom diskon per-baris (sesuai kebutuhan fitur)
                $t->enum('discount_type', ['amount','percent'])->default('amount');
                $t->decimal('discount_value', 15, 2)->default(0);   // input: IDR atau %
                $t->decimal('discount_amount', 15, 2)->default(0);  // rupiah hasil hitung
                $t->decimal('line_subtotal', 15, 2)->default(0);    // qty * unit_price
                $t->decimal('line_total', 15, 2)->default(0);       // setelah diskon baris

                $t->timestamps();

                // Nanti FK bisa ditambahkan setelah yakin tabel quotations siap:
                // $t->foreign('quotation_id')->references('id')->on('quotations')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_lines');
    }
};
