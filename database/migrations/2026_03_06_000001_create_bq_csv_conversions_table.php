<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bq_csv_conversions', function (Blueprint $table) {
            $table->id();
            $table->string('source_category');
            $table->string('source_item');
            $table->string('source_category_norm');
            $table->string('source_item_norm');
            $table->string('mapped_item');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('source_category_norm');
            $table->index('source_item_norm');
            $table->unique(['source_category_norm', 'source_item_norm'], 'bq_csv_conversions_unique_norm');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bq_csv_conversions');
    }
};
