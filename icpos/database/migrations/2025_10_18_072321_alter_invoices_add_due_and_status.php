<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration {
public function up(): void
{
Schema::table('invoices', function (Blueprint $t) {
if (!Schema::hasColumn('invoices','due_date')) {
$t->date('due_date')->nullable()->after('date');
}
if (!Schema::hasColumn('invoices','status')) {
$t->string('status', 20)->default('draft')->change();
}
if (!Schema::hasColumn('invoices','notes')) {
$t->text('notes')->nullable()->after('brand_snapshot');
}
});
}
public function down(): void
{
// no-op safe down
}
};