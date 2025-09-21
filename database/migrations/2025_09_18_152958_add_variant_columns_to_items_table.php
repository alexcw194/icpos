<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            // tipe varian: none|color|size|length|color_size
            $table->string('variant_type', 20)->default('none')->after('brand_id');
            // daftar opsi: {"color":["Blue","Red"], "size":["S","M","L"]} atau {"length":[20,30]}
            $table->json('variant_options')->nullable()->after('variant_type');
            // template nama (opsional), default akan kita handle di model
            $table->string('name_template')->nullable()->after('variant_options');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['variant_type', 'variant_options', 'name_template']);
        });
    }
};
