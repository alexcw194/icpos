<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('companies', function (Blueprint $t) {
            if (!Schema::hasColumn('companies','is_taxable')) {
                $t->boolean('is_taxable')->default(true)->after('name');
            }
            if (!Schema::hasColumn('companies','default_tax_percent')) {
                $t->decimal('default_tax_percent', 5, 2)->default(11.00)->after('is_taxable');
            }
            if (!Schema::hasColumn('companies','alias')) {
                $t->string('alias', 16)->nullable()->after('name');
            }
            if (!Schema::hasColumn('companies','quotation_prefix')) {
                $t->string('quotation_prefix', 10)->default('QO')->after('default_tax_percent');
            }
        });
    }
    public function down(): void {
        Schema::table('companies', function (Blueprint $t) {
            // aman untuk rollback jika diperlukan
            if (Schema::hasColumn('companies','quotation_prefix')) $t->dropColumn('quotation_prefix');
            if (Schema::hasColumn('companies','alias'))            $t->dropColumn('alias');
            if (Schema::hasColumn('companies','default_tax_percent')) $t->dropColumn('default_tax_percent');
            if (Schema::hasColumn('companies','is_taxable'))       $t->dropColumn('is_taxable');
        });
    }
};
