<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('project_quotation_lines', function (Blueprint $t) {
            $t->enum('line_type', ['product', 'charge', 'percent'])
                ->default('product')
                ->after('item_label');
            $t->foreignId('source_template_id')
                ->nullable()
                ->after('line_type')
                ->constrained('bq_line_templates')
                ->nullOnDelete();
            $t->foreignId('source_template_line_id')
                ->nullable()
                ->after('source_template_id')
                ->constrained('bq_line_template_lines')
                ->nullOnDelete();
            $t->decimal('percent_value', 9, 4)->nullable()->after('source_template_line_id');
            $t->enum('basis_type', ['bq_product_total', 'section_product_total'])->nullable()->after('percent_value');
            $t->decimal('computed_amount', 18, 2)->nullable()->after('basis_type');
            $t->boolean('editable_price')->default(true)->after('computed_amount');
            $t->boolean('editable_percent')->default(true)->after('editable_price');
            $t->boolean('can_remove')->default(true)->after('editable_percent');
        });
    }

    public function down(): void
    {
        Schema::table('project_quotation_lines', function (Blueprint $t) {
            $t->dropConstrainedForeignId('source_template_id');
            $t->dropConstrainedForeignId('source_template_line_id');
            $t->dropColumn([
                'line_type',
                'percent_value',
                'basis_type',
                'computed_amount',
                'editable_price',
                'editable_percent',
                'can_remove',
            ]);
        });
    }
};
