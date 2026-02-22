<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index()
    {
        $users = User::orderBy('name')->paginate(20);
        return view('users.index', compact('users'));
    }

    public function create()
    {
        $roles = $this->availableRoles();
        return view('users.create', compact('roles'));
    }

    private function availableRoles(): array
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->where('name', '!=', 'SuperAdmin')
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    public function store(Request $request)
    {
        $request->merge(['roles' => $this->normalizeIncomingRoles($request)]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['nullable', 'boolean'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [
                'required',
                'distinct',
                Rule::exists('roles', 'name')->where(function ($query) {
                    $query->where('guard_name', 'web')->where('name', '!=', 'SuperAdmin');
                }),
            ],
            'email_signature' => ['nullable', 'string'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
            'password' => ['nullable', 'string', 'min:8'],
            'send_invite' => ['nullable', 'boolean'],
        ]);

        $finalRoles = $this->finalizeRoles($data['roles']);
        $sendInvite = (bool) ($data['send_invite'] ?? false);
        $passwordProvided = filled($data['password'] ?? null);

        if (!$passwordProvided && !$sendInvite) {
            return back()
                ->withErrors(['password' => 'Isi password atau centang "Kirim undangan".'])
                ->withInput();
        }

        $u = new User();
        $u->name = $data['name'];
        $u->email = $data['email'];
        $u->phone = $data['phone'] ?? null;
        $u->is_active = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true;
        $u->email_signature = $data['email_signature'] ?? null;

        if ($request->hasFile('avatar')) {
            $u->profile_image_path = $request->file('avatar')->store('avatars', 'public');
        }

        if ($passwordProvided) {
            $u->password = Hash::make($data['password']);
            $u->must_change_password = true;
        } else {
            $u->password = Hash::make(Str::random(40));
            $u->must_change_password = true;
        }

        $u->save();
        $u->syncRoles($finalRoles->all());

        if (!$passwordProvided && $sendInvite) {
            Password::sendResetLink(['email' => $u->email]);
        }

        return redirect()->route('users.index')->with('ok', 'User created.');
    }

    public function edit(User $user)
    {
        $currentRoles = $user->getRoleNames()->values()->all();
        $roles = $this->availableRoles();

        return view('users.edit', compact('user', 'roles', 'currentRoles'));
    }

    public function update(Request $request, User $user)
    {
        $request->merge(['roles' => $this->normalizeIncomingRoles($request)]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email,' . $user->id],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['nullable', 'boolean'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [
                'required',
                'distinct',
                Rule::exists('roles', 'name')->where(function ($query) {
                    $query->where('guard_name', 'web')->where('name', '!=', 'SuperAdmin');
                }),
            ],
            'email_signature' => ['nullable', 'string'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
            'password' => ['nullable', 'string', 'min:8'],
            'send_invite' => ['nullable', 'boolean'],
        ]);

        $finalRoles = $this->finalizeRoles($data['roles']);
        $sendInvite = (bool) ($data['send_invite'] ?? false);
        $passwordProvided = filled($data['password'] ?? null);

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->phone = $data['phone'] ?? null;
        $user->is_active = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $user->is_active;
        $user->email_signature = $data['email_signature'] ?? null;

        if ($request->hasFile('avatar')) {
            if ($user->profile_image_path) {
                Storage::disk('public')->delete($user->profile_image_path);
            }
            $user->profile_image_path = $request->file('avatar')->store('avatars', 'public');
        }

        if ($passwordProvided) {
            $user->password = Hash::make($data['password']);
            $user->must_change_password = false;
        } elseif ($sendInvite) {
            $user->must_change_password = true;
        }

        $user->save();
        $user->syncRoles($finalRoles->all());

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

    private function normalizeIncomingRoles(Request $request): array
    {
        $roles = $request->input('roles');
        if (is_array($roles)) {
            return $roles;
        }

        $legacyRole = $request->input('role');
        if (is_string($legacyRole) && trim($legacyRole) !== '') {
            return [trim($legacyRole)];
        }

        return [];
    }

    private function finalizeRoles(array $rawRoles): Collection
    {
        $roles = collect($rawRoles)
            ->map(fn ($role) => is_string($role) ? trim($role) : '')
            ->filter(fn ($role) => $role !== '')
            ->unique()
            ->values();

        if ($roles->contains('Admin')) {
            return collect(['Admin']);
        }

        if ($roles->isEmpty()) {
            throw ValidationException::withMessages([
                'roles' => 'Pilih minimal satu role.',
            ]);
        }

        return $roles;
    }
}
