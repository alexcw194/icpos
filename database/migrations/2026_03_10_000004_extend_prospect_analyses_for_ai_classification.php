<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('prospect_analyses', function (Blueprint $table) {
            if (!Schema::hasColumn('prospect_analyses', 'ai_status')) {
                $table->enum('ai_status', ['not_run', 'success', 'failed'])
                    ->default('not_run')
                    ->after('status');
            }
            if (!Schema::hasColumn('prospect_analyses', 'ai_provider')) {
                $table->string('ai_provider', 50)->nullable()->after('ai_status');
            }
            if (!Schema::hasColumn('prospect_analyses', 'ai_model')) {
                $table->string('ai_model', 80)->nullable()->after('ai_provider');
            }
            if (!Schema::hasColumn('prospect_analyses', 'ai_industry_label')) {
                $table->string('ai_industry_label', 120)->nullable()->after('ai_model');
            }
            if (!Schema::hasColumn('prospect_analyses', 'ai_sub_industry')) {
                $table->string('ai_sub_industry', 120)->nullable()->after('ai_industry_label');
            }
            if (!Schema::hasColumn('prospect_analyses', 'ai_business_output')) {
                $table->text('ai_business_output')->nullable()->after('ai_sub_industry');
            }
            if (!Schema::hasColumn('prospect_analyses', 'ai_hotel_star')) {
                $table->unsignedTinyInteger('ai_hotel_star')->nullable()->after('ai_business_output');
            }
            if (!Schema::hasColumn('prospect_analyses', 'ai_confidence')) {
                $table->decimal('ai_confidence', 5, 2)->nullable()->after('ai_hotel_star');
            }
            if (!Schema::hasColumn('prospect_analyses', 'ai_payload_json')) {
                $table->json('ai_payload_json')->nullable()->after('ai_confidence');
            }
            if (!Schema::hasColumn('prospect_analyses', 'ai_error_message')) {
                $table->text('ai_error_message')->nullable()->after('ai_payload_json');
            }
        });
    }

    public function down(): void
    {
        Schema::table('prospect_analyses', function (Blueprint $table) {
            if (Schema::hasColumn('prospect_analyses', 'ai_error_message')) {
                $table->dropColumn('ai_error_message');
            }
            if (Schema::hasColumn('prospect_analyses', 'ai_payload_json')) {
                $table->dropColumn('ai_payload_json');
            }
            if (Schema::hasColumn('prospect_analyses', 'ai_confidence')) {
                $table->dropColumn('ai_confidence');
            }
            if (Schema::hasColumn('prospect_analyses', 'ai_hotel_star')) {
                $table->dropColumn('ai_hotel_star');
            }
            if (Schema::hasColumn('prospect_analyses', 'ai_business_output')) {
                $table->dropColumn('ai_business_output');
            }
            if (Schema::hasColumn('prospect_analyses', 'ai_sub_industry')) {
                $table->dropColumn('ai_sub_industry');
            }
            if (Schema::hasColumn('prospect_analyses', 'ai_industry_label')) {
                $table->dropColumn('ai_industry_label');
            }
            if (Schema::hasColumn('prospect_analyses', 'ai_model')) {
                $table->dropColumn('ai_model');
            }
            if (Schema::hasColumn('prospect_analyses', 'ai_provider')) {
                $table->dropColumn('ai_provider');
            }
            if (Schema::hasColumn('prospect_analyses', 'ai_status')) {
                $table->dropColumn('ai_status');
            }
        });
    }
};
