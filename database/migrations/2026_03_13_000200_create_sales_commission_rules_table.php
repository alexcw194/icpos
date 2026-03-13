<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_commission_rules')) {
            return;
        }

        Schema::create('sales_commission_rules', function (Blueprint $table) {
            $table->id();
            $table->enum('scope_type', ['brand', 'family']);
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->string('family_code', 50)->nullable();
            $table->decimal('rate_percent', 8, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['scope_type', 'brand_id']);
            $table->unique(['scope_type', 'family_code']);
            $table->index(['scope_type', 'is_active']);
        });

        if (Schema::hasTable('brands')) {
            $brand = DB::table('brands')
                ->select('id')
                ->whereRaw('LOWER(name) = ?', ['rosenbauer'])
                ->first();

            if ($brand) {
                DB::table('sales_commission_rules')->updateOrInsert(
                    [
                        'scope_type' => 'brand',
                        'brand_id' => $brand->id,
                    ],
                    [
                        'family_code' => null,
                        'rate_percent' => 3,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_commission_rules');
    }
};
