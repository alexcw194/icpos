<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function edit()
    {
        $s = Setting::allKeyed();
        return view('admin.settings.edit', compact('s'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            // ===== Company =====
            'company_name'     => ['required','string','max:150'],
            'company_email'    => ['nullable','email','max:150'],
            'company_phone'    => ['nullable','string','max:50'],
            'company_address'  => ['nullable','string'],
            'company_logo'     => ['nullable','image','mimes:jpg,jpeg,png,webp','max:1024'],

            // ===== Global SMTP =====
            'mail_host'        => ['nullable','string','max:150'],
            'mail_port'        => ['nullable','integer'],
            'mail_encryption'  => ['nullable','in:tls,ssl,null'],
            'mail_from_address'=> ['nullable','email'],
            'mail_from_name'   => ['nullable','string','max:150'],

            // ===== Username policy =====
            'mail_username_policy' => ['required','in:force_email,default_email,custom_only'],
        ]);

        // Upload logo
        $logoPath = Setting::get('company.logo_path');
        if ($request->hasFile('company_logo')) {
            if ($logoPath) Storage::disk('public')->delete($logoPath);
            $logoPath = $request->file('company_logo')->store('company','public');
        }

        // Enkripsi: '' = tanpa enkripsi
        $enc = $validated['mail_encryption'] ?? 'tls';
        if ($enc === 'null') $enc = '';

        // From Name: simpan '' jika dikosongi (nanti pakai nama user saat kirim)
        $fromName = isset($validated['mail_from_name']) && $validated['mail_from_name'] !== ''
            ? $validated['mail_from_name']
            : '';

        Setting::setMany([
            // Company
            'company.name'        => $validated['company_name'],
            'company.email'       => $validated['company_email'] ?? '',
            'company.phone'       => $validated['company_phone'] ?? '',
            'company.address'     => $validated['company_address'] ?? '',
            'company.logo_path'   => $logoPath,

            // Mail (global)
            'mail.host'           => $validated['mail_host'] ?? '',
            'mail.port'           => (string)($validated['mail_port'] ?? '587'),
            'mail.encryption'     => $enc,
            'mail.from.address'   => $validated['mail_from_address'] ?? '',
            'mail.from.name'      => $fromName,

            // >>> FIX: simpan ke key dengan DOT, bukan underscore
            'mail.username_policy'=> $validated['mail_username_policy'],
        ]);

        return redirect()
            ->route('dashboard')
            ->with('ok', 'Settings updated.');
        }
}
