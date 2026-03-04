<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;
use App\Models\Setting;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('lead-discovery:scan')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->when(function (): bool {
        try {
            if (!Schema::hasTable('settings')) {
                return false;
            }

            return (int) Setting::get('lead_discovery.enabled', 0) === 1;
        } catch (\Throwable $e) {
            return false;
        }
    });
