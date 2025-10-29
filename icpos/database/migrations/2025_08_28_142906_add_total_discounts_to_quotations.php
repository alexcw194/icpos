<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration {
public function up(): void {
Schema::table('quotations', function (Blueprint $t) {
$t->enum('total_discount_type', ['amount','percent'])->default('amount')->after('notes');
$t->decimal('total_discount_value', 15, 2)->default(0)->after('total_discount_type');
$t->decimal('total_discount_amount', 15, 2)->default(0)->after('total_discount_value');


$t->decimal('lines_subtotal', 15, 2)->default(0)->after('total_discount_amount');
$t->decimal('taxable_base', 15, 2)->default(0)->after('lines_subtotal');


// Jika kolom2 berikut belum ada, boleh aktifkan:
// $t->decimal('tax_percent', 5, 2)->default(0)->after('taxable_base');
// $t->decimal('tax_amount', 15, 2)->default(0)->after('tax_percent');
// $t->decimal('total', 15, 2)->default(0)->after('tax_amount');
});
}
public function down(): void {
Schema::table('quotations', function (Blueprint $t) {
$t->dropColumn([
'total_discount_type','total_discount_value','total_discount_amount',
'lines_subtotal','taxable_base'
]);
});
}
};