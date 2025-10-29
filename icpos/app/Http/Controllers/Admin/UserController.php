<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
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

        $u = new User();
        $u->name = $data['name'];
        $u->email = $data['email'];
        $u->is_active = (bool)($data['is_active'] ?? true);
        $u->email_signature = $data['email_signature'] ?? null;

        if ($request->hasFile('avatar')) {
            $u->profile_image_path = $request->file('avatar')->store('avatars','public');
        }
        if (!empty($data['password'])) {
            $u->password = Hash::make($data['password']);
            $u->must_change_password = true;
        }

        $u->save();
        $u->syncRoles([$data['role']]);

        if (empty($data['password']) && ($data['send_invite'] ?? false)) {
            Password::sendResetLink(['email' => $u->email]); // user set password sendiri
            $u->forceFill(['must_change_password' => true])->save();
        }

        return redirect()->route('users.index')->with('ok','User created.');
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

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->is_active = (bool)($data['is_active'] ?? false);
        $user->email_signature = $data['email_signature'] ?? null;

        if ($request->hasFile('avatar')) {
            if ($user->profile_image_path) Storage::disk('public')->delete($user->profile_image_path);
            $user->profile_image_path = $request->file('avatar')->store('avatars','public');
        }

        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
            $user->must_change_password = true;
        }

        $user->save();
        $user->syncRoles([$data['role']]);

        if (empty($data['password']) && ($data['send_invite'] ?? false)) {
            Password::sendResetLink(['email' => $user->email]);
            $user->forceFill(['must_change_password' => true])->save();
        }

        return redirect()->route('users.index')->with('ok','User updated.');
    }
}
