<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('project_quotations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $t->foreignId('company_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $t->foreignId('customer_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $t->string('number')->unique();
            $t->unsignedInteger('version')->default(1);
            $t->enum('status', ['draft', 'issued', 'won', 'lost', 'superseded'])->default('draft');
            $t->date('quotation_date');
            $t->string('to_name');
            $t->string('attn_name')->nullable();
            $t->string('project_title');
            $t->unsignedInteger('working_time_days')->nullable();
            $t->unsignedInteger('working_time_hours_per_day')->default(8);
            $t->unsignedInteger('validity_days')->default(15);
            $t->boolean('tax_enabled')->default(false);
            $t->decimal('tax_percent', 5, 2)->default(0);
            $t->decimal('subtotal_material', 18, 2)->default(0);
            $t->decimal('subtotal_labor', 18, 2)->default(0);
            $t->decimal('subtotal', 18, 2)->default(0);
            $t->decimal('tax_amount', 18, 2)->default(0);
            $t->decimal('grand_total', 18, 2)->default(0);
            $t->longText('notes')->nullable();
            $t->string('signatory_name')->nullable();
            $t->string('signatory_title')->nullable();
            $t->dateTime('issued_at')->nullable();
            $t->dateTime('won_at')->nullable();
            $t->dateTime('lost_at')->nullable();
            $t->foreignId('sales_owner_user_id')->constrained('users')->restrictOnDelete();
            $t->json('brand_snapshot')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_quotations');
    }
};
