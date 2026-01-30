<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    /**
     * Guard untuk Spatie Permission.
     */
    protected $guard_name = 'web';

    /**
     * Kolom yang bisa diisi mass-assignment.
     * (role_id dibiarkan untuk kompatibilitas lama; Spatie tetap pakai pivot roles)
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'company_id',
        'role_id',                 // legacy (opsional)
        'is_active',
        'profile_image_path',
        'email_signature',
        'email_cc_self',

        // --- SMTP per-user ---
        'smtp_username',           // email pengirim milik user
        'smtp_password',           // terenkripsi via casts (tidak ditampilkan)

        // --- kebijakan password / audit ---
        'must_change_password',
        'password_changed_at',
        'last_login_at',
    ];

    /**
     * Kolom yang disembunyikan saat serialisasi.
     */
    protected $hidden = [
        'password',
        'remember_token',
        'smtp_password',           // jangan bocorkan ke response/json
    ];

    /**
     * Casting atribut.
     * - Laravel otomatis hash kolom `password` karena cast 'hashed'
     * - `smtp_password` dienkripsi transparan (encrypt-at-rest)
     */
    protected function casts(): array
    {
        return [
            'email_verified_at'    => 'datetime',
            'password'             => 'hashed',
            'is_active'            => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at'        => 'datetime',
            'password_changed_at'  => 'datetime',
            'email_cc_self'        => 'boolean',

            // SMTP per-user
            'smtp_password'        => 'encrypted',
        ];
    }

    /**
     * Relasi ke Company.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function isSuperAdmin(): bool
    {
        // role khusus Super Admin / atau permission global
        return $this->hasRole('SuperAdmin') || $this->can('manage everything');
    }

    public function isAdmin(): bool
    {
        return $this->hasAnyRole(['Admin', 'SuperAdmin']) || $this->can('manage everything');
    }

    public function isAdminStrict(): bool
    {
        return $this->hasRole('Admin');
    }

    /**
     * (Legacy) Jika masih memakai role tunggal.
     * Sudah tidak diperlukan bila pakai Spatie HasRoles sepenuhnya.
     */
    // public function role()
    // {
    //     return $this->belongsTo(Role::class);
    // }

    /**
     * Accessor URL avatar (opsional, memudahkan di Blade).
     *
     * $user->profile_image_url
     */
    public function getProfileImageUrlAttribute(): ?string
    {
        if (!$this->profile_image_path) {
            return null;
        }
        return asset('storage/' . $this->profile_image_path);
    }
}
