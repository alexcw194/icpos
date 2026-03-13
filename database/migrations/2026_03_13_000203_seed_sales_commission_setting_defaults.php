<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        $now = now();
        $defaults = [
            'sales.commission.default_rate_percent' => '5',
            'sales.commission.project.fire_alarm_rate_percent' => '5',
            'sales.commission.project.fire_hydrant_rate_percent' => '1.5',
            'sales.commission.project.maintenance_rate_percent' => '5',
        ];

        foreach ($defaults as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => $now, 'created_at' => $now]
            );
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')
            ->whereIn('key', [
                'sales.commission.default_rate_percent',
                'sales.commission.project.fire_alarm_rate_percent',
                'sales.commission.project.fire_hydrant_rate_percent',
                'sales.commission.project.maintenance_rate_percent',
            ])
            ->delete();
    }
};
