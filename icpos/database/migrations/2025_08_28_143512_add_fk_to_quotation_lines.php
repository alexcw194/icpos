<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_lines', function (Blueprint $t) {
            // Pastikan kolom quotation_id ada (harusnya sudah ada dari create_quotation_lines_table)
            if (!Schema::hasColumn('quotation_lines', 'quotation_id')) {
                $t->unsignedBigInteger('quotation_id')->index();
            }

            // Tambah FK dengan nama constraint yang eksplisit agar mudah di-drop saat rollback
            $t->foreign('quotation_id', 'ql_quotation_id_fk')
              ->references('id')->on('quotations')
              ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quotation_lines', function (Blueprint $t) {
            // Drop FK sesuai nama yang kita set di atas
            // Jika ini gagal di environment tertentu, alternatifnya: $t->dropForeign(['quotation_id']);
            $t->dropForeign('ql_quotation_id_fk');
        });
    }
};
