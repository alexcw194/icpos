<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordController extends Controller
{
    /**
     * Tampilkan form ganti password.
     */
    public function edit(): View
    {
        return view('auth.change-password'); // <-- sesuai strukturmu
    }

    /**
     * Update password user.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Jika user sedang DIPAKSA ganti password (must_change_password = true), tak perlu current_password.
        // Kalau tidak dipaksa, wajib isi current_password.
        $rules = [
            'password' => ['required', Password::defaults(), 'confirmed'],
        ];
        if (!$user->must_change_password) {
            $rules['current_password'] = ['required', 'current_password'];
        }

        $validated = $request->validateWithBag('updatePassword', $rules);

        $user->forceFill([
            'password'              => Hash::make($validated['password']), // (cast 'hashed' juga boleh)
            'must_change_password'  => false,
            'password_changed_at'   => now(),
        ])->save();

        return redirect()->route('dashboard')->with('ok', 'Password updated.');
    }
}
