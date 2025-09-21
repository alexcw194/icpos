<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $t) {
            if (!Schema::hasColumn('companies','alias'))                 $t->string('alias',16)->nullable()->after('name');
            if (!Schema::hasColumn('companies','is_taxable'))            $t->boolean('is_taxable')->default(true)->after('alias');
            if (!Schema::hasColumn('companies','default_tax_percent'))   $t->decimal('default_tax_percent',5,2)->default(11.00)->after('is_taxable');

            if (!Schema::hasColumn('companies','quotation_prefix'))      $t->string('quotation_prefix',10)->default('QO')->after('default_tax_percent');
            if (!Schema::hasColumn('companies','invoice_prefix'))        $t->string('invoice_prefix',10)->default('INV')->after('quotation_prefix');
            if (!Schema::hasColumn('companies','delivery_prefix'))       $t->string('delivery_prefix',10)->default('DO')->after('invoice_prefix');

            if (!Schema::hasColumn('companies','logo_path'))             $t->string('logo_path')->nullable()->after('delivery_prefix');
            if (!Schema::hasColumn('companies','address'))               $t->text('address')->nullable()->after('logo_path');
            if (!Schema::hasColumn('companies','tax_id'))                $t->string('tax_id',64)->nullable()->after('address');
            if (!Schema::hasColumn('companies','phone'))                 $t->string('phone',64)->nullable()->after('tax_id');
            if (!Schema::hasColumn('companies','email'))                 $t->string('email',128)->nullable()->after('phone');

            if (!Schema::hasColumn('companies','bank_name'))             $t->string('bank_name',128)->nullable()->after('email');
            if (!Schema::hasColumn('companies','bank_account_name'))     $t->string('bank_account_name',128)->nullable()->after('bank_name');
            if (!Schema::hasColumn('companies','bank_account_no'))       $t->string('bank_account_no',64)->nullable()->after('bank_account_name');
            if (!Schema::hasColumn('companies','bank_account_branch'))   $t->string('bank_account_branch',128)->nullable()->after('bank_account_no');

            if (!Schema::hasColumn('companies','quotation_template'))    $t->string('quotation_template',32)->default('default')->after('bank_account_branch');
            if (!Schema::hasColumn('companies','invoice_template'))      $t->string('invoice_template',32)->default('default')->after('quotation_template');
            if (!Schema::hasColumn('companies','delivery_template'))     $t->string('delivery_template',32)->default('default')->after('invoice_template');
        });
    }

    public function down(): void
    {
        // biasanya tidak perlu rollback di produksi; kosongkan saja atau drop kolom satu2 kalau perlu
    }
};
