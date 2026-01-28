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
