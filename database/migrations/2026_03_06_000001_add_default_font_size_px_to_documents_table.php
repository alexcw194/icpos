<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (!Schema::hasColumn('documents', 'default_font_size_px')) {
                $table->unsignedSmallInteger('default_font_size_px')
                    ->nullable()
                    ->after('body_html');
            }
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (Schema::hasColumn('documents', 'default_font_size_px')) {
                $table->dropColumn('default_font_size_px');
            }
        });
    }
};
