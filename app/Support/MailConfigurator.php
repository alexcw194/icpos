<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;

class MailConfigurator
{
    public static function applyUser(User $user): void
    {
        // Global settings (fallback ke config)
        $host = Setting::get('mail.host', config('mail.mailers.smtp.host'));
        $port = (int) Setting::get('mail.port', config('mail.mailers.smtp.port'));
        $enc  = Setting::get('mail.encryption', config('mail.mailers.smtp.encryption'));
        $policy = Setting::get('mail.username_policy', 'default_email');

        // Tentukan username sesuai policy
        if ($policy === 'force_email') {
            $username = $user->email;
        } elseif ($policy === 'custom_only') {
            $username = $user->smtp_username; // validasi wajib ada dilakukan di Controller aksi kirim
        } else { // default_email
            $username = $user->smtp_username ?: $user->email;
        }

        // Password SMTP milik user (terenkripsi via casts, otomatis didekripsi saat diakses)
        $password = $user->smtp_password;

        // Terapkan runtime config untuk mailer SMTP
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp' => [
                'transport'  => 'smtp',
                'host'       => $host,
                'port'       => $port,
                'encryption' => $enc,
                'username'   => $username,
                'password'   => $password,
                'timeout'    => null,
            ],
            // From selalu email user (sesuai keputusanmu)
            'mail.from.address' => $user->email,
            'mail.from.name'    => $user->name ?: config('app.name'),
        ]);
    }
}
