<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('prospect_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospect_id')->constrained('prospects')->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['queued', 'running', 'success', 'failed'])->default('queued')->index();
            $table->string('website_url', 255)->nullable();
            $table->unsignedSmallInteger('website_http_status')->nullable();
            $table->boolean('website_reachable')->default(false);
            $table->unsignedSmallInteger('pages_crawled')->default(0);
            $table->json('crawled_urls_json')->nullable();
            $table->json('emails_json')->nullable();
            $table->json('phones_json')->nullable();
            $table->text('linkedin_company_url')->nullable();
            $table->json('linkedin_people_json')->nullable();
            $table->string('business_type', 120)->nullable();
            $table->json('business_signals_json')->nullable();
            $table->enum('address_clarity', ['clear', 'partial', 'missing'])->default('missing');
            $table->json('checklist_json')->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['prospect_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospect_analyses');
    }
};
