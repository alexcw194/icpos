<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('customer_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $t->string('code')->unique();
            $t->string('name');
            $t->json('systems_json')->nullable();
            $t->enum('status', ['draft', 'active', 'closed', 'cancelled'])->default('draft');
            $t->foreignId('sales_owner_user_id')->constrained('users')->restrictOnDelete();
            $t->date('start_date')->nullable();
            $t->date('target_finish_date')->nullable();
            $t->decimal('contract_value_baseline', 18, 2)->default(0);
            $t->decimal('contract_value_current', 18, 2)->default(0);
            $t->text('notes')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
