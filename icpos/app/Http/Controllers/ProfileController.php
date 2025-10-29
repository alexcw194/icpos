<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Tampilkan form profil.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();

        // Kirim juga policy agar view bisa men-disable input username bila perlu
        try {
            $policy = \App\Models\Setting::get('mail.username_policy', 'default_email');
        } catch (\Throwable $e) {
            $policy = 'default_email';
        }

        return view('profile.edit', [
            'user'              => $user,
            'mailPolicy'        => $policy,
            'username_readonly' => $policy === 'force_email',
        ]);
    }

    /**
     * Update informasi profil user.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Ambil kebijakan global
        try {
            $policy = \App\Models\Setting::get('mail.username_policy', 'default_email');
        } catch (\Throwable $e) {
            $policy = 'default_email';
        }

        // VALIDASI dasar
        $rules = [
            'name'            => ['required', 'string', 'max:255'],
            'email'           => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'email_signature' => ['nullable', 'string'],
            'smtp_password'   => ['nullable', 'string'],   // kosong = tidak mengubah
            'email_cc_self'   => ['sometimes', 'boolean'], // toggle CC ke diri sendiri
        ];

        // VALIDASI untuk username sesuai policy
        if ($policy === 'force_email') {
            // tidak ada input smtp_username
        } elseif ($policy === 'custom_only') {
            $rules['smtp_username'] = ['required', 'string', 'max:255'];
        } else { // default_email
            $rules['smtp_username'] = ['nullable', 'string', 'max:255'];
        }

        $data = $request->validate($rules, [], [
            'smtp_username' => 'SMTP Username',
            'smtp_password' => 'SMTP Password',
        ]);

        // Isi field umum
        $user->fill([
            'name'            => $data['name'],
            'email'           => $data['email'],
            'email_signature' => $data['email_signature'] ?? null,
        ]);

        // Simpan preferensi CC ke diri sendiri (unchecked -> false)
        $user->email_cc_self = $request->boolean('email_cc_self');

        // Simpan username sesuai policy
        if ($policy === 'force_email') {
            // Paksa email sebagai username; simpan null agar resolver pakai email user
            $user->smtp_username = null;
        } elseif ($policy === 'custom_only') {
            $user->smtp_username = $data['smtp_username']; // required, aman
        } else { // default_email
            // Bila tidak diinput, jadikan null agar runtime pakai email user
            $user->smtp_username = $data['smtp_username'] ?? null;
        }

        // Ubah password SMTP hanya bila diisi
        if ($request->filled('smtp_password')) {
            $user->smtp_password = $data['smtp_password'];
        }

        $user->save();

        return back()->with('success', 'Profil diperbarui.');
    }

    /**
     * Hapus akun user.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
