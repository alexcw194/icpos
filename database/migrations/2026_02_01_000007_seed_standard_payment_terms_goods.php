<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('term_of_payments') || !Schema::hasTable('payment_term_schedules')) {
            return;
        }

        // Adjust FK first to allow altering referenced column length
        if (Schema::hasTable('so_billing_terms')) {
            try { DB::statement("ALTER TABLE `so_billing_terms` DROP FOREIGN KEY `so_billing_terms_top_code_foreign`"); } catch (\Throwable $e) {}
        }

        // Ensure code column length supports template codes
        try {
            DB::statement("ALTER TABLE `term_of_payments` MODIFY `code` VARCHAR(64)");
        } catch (\Throwable $e) {
            // ignore
        }

        // Adjust SO billing term code length + FK
        if (Schema::hasTable('so_billing_terms')) {
            try { DB::statement("ALTER TABLE `so_billing_terms` MODIFY `top_code` VARCHAR(64)"); } catch (\Throwable $e) {}
            try {
                DB::statement("ALTER TABLE `so_billing_terms` ADD CONSTRAINT `so_billing_terms_top_code_foreign` FOREIGN KEY (`top_code`) REFERENCES `term_of_payments`(`code`) ON UPDATE CASCADE ON DELETE RESTRICT");
            } catch (\Throwable $e) {}
        }

        // Adjust project quotation payment terms (if exists)
        if (Schema::hasTable('project_quotation_payment_terms')) {
            try { DB::statement("ALTER TABLE `project_quotation_payment_terms` MODIFY `code` VARCHAR(64)"); } catch (\Throwable $e) {}
        }

        $now = now();
        $templates = [
            [
                'code' => 'DP50_BALANCE_ON_DELIVERY',
                'description' => 'DP 50% + Balance on Delivery',
                'applicable_to' => json_encode(['goods']),
                'schedules' => [
                    ['sequence' => 1, 'portion_type' => 'percent', 'portion_value' => 50, 'due_trigger' => 'on_so'],
                    ['sequence' => 2, 'portion_type' => 'percent', 'portion_value' => 50, 'due_trigger' => 'on_delivery'],
                ],
            ],
            [
                'code' => 'NET14',
                'description' => 'Net 14 Days',
                'applicable_to' => json_encode(['goods']),
                'schedules' => [
                    ['sequence' => 1, 'portion_type' => 'percent', 'portion_value' => 100, 'due_trigger' => 'after_invoice_days', 'offset_days' => 14],
                ],
            ],
            [
                'code' => 'NET30',
                'description' => 'Net 30 Days',
                'applicable_to' => json_encode(['goods']),
                'schedules' => [
                    ['sequence' => 1, 'portion_type' => 'percent', 'portion_value' => 100, 'due_trigger' => 'after_invoice_days', 'offset_days' => 30],
                ],
            ],
            [
                'code' => 'NET45',
                'description' => 'Net 45 Days',
                'applicable_to' => json_encode(['goods']),
                'schedules' => [
                    ['sequence' => 1, 'portion_type' => 'percent', 'portion_value' => 100, 'due_trigger' => 'after_invoice_days', 'offset_days' => 45],
                ],
            ],
            [
                'code' => 'EOM20',
                'description' => 'End of Month, Day 20',
                'applicable_to' => json_encode(['goods']),
                'schedules' => [
                    ['sequence' => 1, 'portion_type' => 'percent', 'portion_value' => 100, 'due_trigger' => 'end_of_month', 'specific_day' => 20],
                ],
            ],
        ];

        foreach ($templates as $tpl) {
            DB::table('term_of_payments')->updateOrInsert(
                ['code' => $tpl['code']],
                [
                    'description' => $tpl['description'],
                    'is_active' => true,
                    'applicable_to' => $tpl['applicable_to'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            $termId = DB::table('term_of_payments')->where('code', $tpl['code'])->value('id');
            if (!$termId) {
                continue;
            }

            $existingCount = DB::table('payment_term_schedules')
                ->where('payment_term_id', $termId)
                ->count();
            if ($existingCount > 0) {
                continue;
            }

            foreach ($tpl['schedules'] as $row) {
                DB::table('payment_term_schedules')->insert([
                    'payment_term_id' => $termId,
                    'sequence' => $row['sequence'],
                    'portion_type' => $row['portion_type'],
                    'portion_value' => $row['portion_value'],
                    'due_trigger' => $row['due_trigger'],
                    'offset_days' => $row['offset_days'] ?? null,
                    'specific_day' => $row['specific_day'] ?? null,
                    'notes' => $row['notes'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // no-op: keep user data
    }
};
