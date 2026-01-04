<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;


class UserController extends Controller
{
    public function index()
    {
        $users = User::orderBy('name')->paginate(20);
        return view('users.index', compact('users'));
    }

    public function create()
    {
        $roles = ['Admin','Sales','Finance']; // SuperAdmin tidak ditampilkan untuk umum
        return view('users.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'   => ['required','string','max:120'],
            'email'  => ['required','email','max:190','unique:users,email'],
            'is_active' => ['nullable','boolean'],
            'role'   => ['required', Rule::in(['Admin','Sales','Finance'])],
            'email_signature' => ['nullable','string'],
            'avatar' => ['nullable','image','mimes:jpg,jpeg,png,webp','max:1024'],
            'password' => ['nullable','string','min:8'],
            'send_invite' => ['nullable','boolean'],
        ]);

        $sendInvite = (bool)($data['send_invite'] ?? false);
        $passwordProvided = filled($data['password'] ?? null);

        // Guard: minimal policy
        // - If password not provided, we require invite checkbox to avoid "unknown password" user.
        //   (kalau kamu mau allow tanpa invite, hapus block ini)
        if (!$passwordProvided && !$sendInvite) {
            return back()
                ->withErrors(['password' => 'Isi password atau centang "Send Invite".'])
                ->withInput();
        }

        $u = new User();
        $u->name = $data['name'];
        $u->email = $data['email'];
        $u->is_active = array_key_exists('is_active', $data) ? (bool)$data['is_active'] : true;
        $u->email_signature = $data['email_signature'] ?? null;

        if ($request->hasFile('avatar')) {
            $u->profile_image_path = $request->file('avatar')->store('avatars', 'public');
        }

        // Password handling
        if ($passwordProvided) {
            // Admin sets password now
            $u->password = Hash::make($data['password']);
            $u->must_change_password = false; // password sudah ditentukan
        } else {
            // Invite flow: set placeholder so DB insert passes, user will set via reset link
            $u->password = Hash::make(Str::random(32));
            $u->must_change_password = true;
        }

        $u->save();
        $u->syncRoles([$data['role']]);

        // Invite email (reset link)
        if (!$passwordProvided && $sendInvite) {
            Password::sendResetLink(['email' => $u->email]);
        }

        return redirect()->route('users.index')->with('ok', 'User created.');
    }


    public function edit(User $user)
    {
        $roles = ['Admin','Sales','Finance'];
        return view('users.edit', compact('user','roles'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'   => ['required','string','max:120'],
            'email'  => ['required','email','max:190','unique:users,email,'.$user->id],
            'is_active' => ['nullable','boolean'],
            'role'   => ['required', Rule::in(['Admin','Sales','Finance'])],
            'email_signature' => ['nullable','string'],
            'avatar' => ['nullable','image','mimes:jpg,jpeg,png,webp','max:1024'],
            'password' => ['nullable','string','min:8'],
            'send_invite' => ['nullable','boolean'],
        ]);

        $sendInvite = (bool)($data['send_invite'] ?? false);
        $passwordProvided = filled($data['password'] ?? null);

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->is_active = array_key_exists('is_active', $data) ? (bool)$data['is_active'] : $user->is_active;
        $user->email_signature = $data['email_signature'] ?? null;

        if ($request->hasFile('avatar')) {
            if ($user->profile_image_path) {
                Storage::disk('public')->delete($user->profile_image_path);
            }
            $user->profile_image_path = $request->file('avatar')->store('avatars', 'public');
        }

        // Password handling
        if ($passwordProvided) {
            $user->password = Hash::make($data['password']);
            $user->must_change_password = false;
        } elseif ($sendInvite) {
            // password tidak diubah, tapi paksa user set password lewat link
            $user->must_change_password = true;
        }
        // kalau password kosong & send_invite false -> no-op (password tetap, flag tetap)

        $user->save();
        $user->syncRoles([$data['role']]);

        if (!$passwordProvided && $sendInvite) {
            Password::sendResetLink(['email' => $user->email]);
        }

        return redirect()->route('users.index')->with('ok', 'User updated.');
    }

    public function destroy(User $user)
    {
        if ($user->profile_image_path) {
            Storage::disk('public')->delete($user->profile_image_path);
        }
        $user->delete();
        return redirect()->route('users.index')->with('ok', 'User deleted.');
    }
}