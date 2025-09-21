<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        // Cek status aktif sebelum mencoba autentikasi
        $user = User::where('email', $request->input('email'))->first();
        if ($user && !$user->is_active) {
            return back()->withErrors(['email' => 'Akun nonaktif. Hubungi admin.']);
        }

        // Proses autentikasi standar (Breeze)
        $request->authenticate();
        $request->session()->regenerate();

        // Update last login
        $request->user()->forceFill(['last_login_at' => now()])->save();

        // Paksa ganti password jika ditandai
        if ($request->user()->must_change_password) {
            return redirect()->route('password.change');
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
