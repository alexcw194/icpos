<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('prospect_apollo_enrichments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospect_id')->constrained('prospects')->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['queued', 'running', 'success', 'failed'])->default('queued')->index();
            $table->string('seed_website', 255)->nullable();
            $table->string('seed_domain', 190)->nullable();
            $table->enum('matched_by', ['domain', 'name_location', 'none'])->default('none');
            $table->string('apollo_org_id', 120)->nullable();
            $table->string('apollo_org_name', 255)->nullable();
            $table->string('apollo_domain', 190)->nullable();
            $table->string('apollo_website_url', 255)->nullable();
            $table->text('apollo_linkedin_url')->nullable();
            $table->string('apollo_industry', 120)->nullable();
            $table->string('apollo_sub_industry', 120)->nullable();
            $table->text('apollo_business_output')->nullable();
            $table->string('apollo_employee_range', 40)->nullable();
            $table->string('apollo_city', 120)->nullable();
            $table->string('apollo_state', 120)->nullable();
            $table->string('apollo_country', 120)->nullable();
            $table->json('apollo_people_json')->nullable();
            $table->json('apollo_payload_json')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['prospect_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospect_apollo_enrichments');
    }
};
