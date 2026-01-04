<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Mail;
use App\Models\Setting;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $logoPath = null;

        if (Schema::hasTable('settings')) {
            config([
                'mail.mailers.smtp.host'       => Setting::get('mail.host',       config('mail.mailers.smtp.host')),
                'mail.mailers.smtp.port'       => (int) Setting::get('mail.port', config('mail.mailers.smtp.port')),
                'mail.mailers.smtp.username'   => Setting::get('mail.username',   config('mail.mailers.smtp.username')),
                'mail.mailers.smtp.password'   => Setting::get('mail.password',   config('mail.mailers.smtp.password')),
                'mail.mailers.smtp.encryption' => Setting::get('mail.encryption', config('mail.mailers.smtp.encryption')),
                'mail.from.address'            => Setting::get('mail.from.address', config('mail.from.address')),
                'mail.from.name'               => Setting::get('mail.from.name',    config('mail.from.name')),
            ]);

            config(['app.name' => Setting::get('company.name', config('app.name'))]);

            $logoPath = Setting::get('company.logo_path');
        }

        // ✅ FIX #2: hard fallback + enforce From (wajib biar sendResetLink() gak 500)
        $fromAddress = config('mail.from.address') ?: env('MAIL_FROM_ADDRESS', 'no-reply@inderacipta.com');
        $fromName    = config('mail.from.name') ?: env('MAIL_FROM_NAME', config('app.name', 'ICPOS'));

        config([
            'mail.from.address' => $fromAddress,
            'mail.from.name'    => $fromName,
        ]);

        Mail::alwaysFrom($fromAddress, $fromName);

        $brandLogoUrl = $logoPath
            ? asset('storage/' . $logoPath)
            : asset('images/logo-default.svg');

        View::share('brandLogoUrl', $brandLogoUrl);

        Schema::defaultStringLength(191);
    }
}
