<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('items', function (Blueprint $t) {
      if (Schema::hasColumn('items','name'))    { $t->index('name',    'items_name_idx'); }
      if (Schema::hasColumn('items','unit_id')) { $t->index('unit_id', 'items_unit_idx'); }
      if (Schema::hasColumn('items','brand_id')){ $t->index('brand_id','items_brand_idx'); }
      if (Schema::hasColumn('items','sku'))     { $t->unique('sku',    'items_sku_uniq'); }
    });
  }

  public function down(): void {
    Schema::table('items', function (Blueprint $t) {
      // pakai nama yang sama dengan up()
      try { $t->dropIndex('items_name_idx'); } catch (\Throwable $e) {}
      try { $t->dropIndex('items_unit_idx'); } catch (\Throwable $e) {}
      try { $t->dropIndex('items_brand_idx'); } catch (\Throwable $e) {}
      try { $t->dropUnique('items_sku_uniq'); } catch (\Throwable $e) {}
    });
  }
};
