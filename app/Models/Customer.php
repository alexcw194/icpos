<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'npwp',
        'address',
        'city',
        'province',
        'country',
        'website',
        'billing_terms_days',
        'notes',
        'jenis_id',              // 🔰 master Jenis (pengganti "industry")
        'created_by',
        'sales_user_id',         // ✅ owner sales

        'npwp_number','npwp_name','npwp_address',

        // ===============================
        // Billing & Shipping (baru)
        // ===============================
        'billing_street',
        'billing_city',
        'billing_state',
        'billing_zip',
        'billing_country',
        'billing_notes',

        'shipping_street',
        'shipping_city',
        'shipping_state',
        'shipping_zip',
        'shipping_country',
        'shipping_notes',
    ];

    protected $casts = [
        'billing_terms_days' => 'integer',
    ];

    /* =========================
     * RELATIONS
     * ========================= */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function salesOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_user_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function jenis(): BelongsTo
    {
        return $this->belongsTo(Jenis::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(\App\Models\Quotation::class);
    }

    public function salesOrders()
    {
        return $this->hasMany(\App\Models\SalesOrder::class);
    }

    /* =========================
     * SCOPES
     * ========================= */
    /** Urutan default (nama ASC) */
    public function scopeOrdered($q)
    {
        return $q->orderBy('name');
    }

    /** Pencarian sederhana by name/email/phone */
    public function scopeKeyword($q, ?string $kw)
    {
        $kw = trim((string) $kw);
        if ($kw === '') return $q;

        return $q->where(function ($w) use ($kw) {
            $w->where('name', 'like', "%{$kw}%")
              ->orWhere('email', 'like', "%{$kw}%")
              ->orWhere('phone', 'like', "%{$kw}%");
        });
    }

    /** Filter berdasarkan Jenis */
    public function scopeInJenis($q, $jenisId)
    {
        return $jenisId ? $q->where('jenis_id', $jenisId) : $q;
    }

    /**
     * VISIBILITY:
     * - Admin / SuperAdmin / Finance => lihat semua customer
     * - Role lain (Sales, dll) => hanya customer miliknya (sales_user_id = user)
     */
    public function scopeVisibleTo($q, $user = null)
    {
        $u = $user ?: auth()->user();
        if (!$u) return $q->whereRaw('1=0');

        if ($u->hasAnyRole(['Admin', 'SuperAdmin', 'Finance'])) {
            return $q;
        }

        // default: hanya miliknya
        return $q->where(function ($w) use ($u) {
            $w->where('sales_user_id', $u->id)
              ->orWhere(function ($w2) use ($u) {
                  // fallback legacy data yang belum punya sales_user_id
                  $w2->whereNull('sales_user_id')
                     ->where('created_by', $u->id);
              });
        });
    }

    /* =========================
     * BOOT HOOKS
     * ========================= */
    protected static function booted()
    {
        static::creating(function (Customer $c) {
            if (!$c->created_by) {
                $c->created_by = auth()->id();
            }

            // default owner:
            // - kalau user Sales, owner = dia
            // - kalau bukan Sales dan belum dipilih, biarkan null (admin wajib pilih via UI)
            if (!$c->sales_user_id && auth()->check() && auth()->user()?->hasRole('Sales')) {
                $c->sales_user_id = auth()->id();
            }

            $c->name_key = self::makeNameKey($c->name ?? '');
        });

        static::updating(function (Customer $c) {
            if ($c->isDirty('name')) {
                $c->name_key = self::makeNameKey($c->name ?? '');
            }
        });
    }

    /* =========================
     * NORMALIZERS (dupe-check & input bersih)
     * ========================= */
    public static function makeNameKey(string $name): string
    {
        $s = mb_strtolower($name);
        $s = preg_replace('/\b(pt|cv|tbk)\b\.?/u', '', $s); // buang badge badan usaha
        $s = preg_replace('/[^a-z0-9\s]/u', ' ', $s);       // non-alnum -> spasi
        $s = preg_replace('/\s+/', ' ', trim($s));          // rapikan spasi
        return $s;
    }

    /** Trim nama */
    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn ($v) => is_string($v) ? trim($v) : $v
        );
    }

    /** Lowercase & trim email */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn ($v) => is_string($v) ? mb_strtolower(trim($v)) : $v
        );
    }

    /** Normalisasi website: trim & hapus trailing slash */
    protected function website(): Attribute
    {
        return Attribute::make(
            set: function ($v) {
                if (!is_string($v)) return $v;
                $u = trim($v);
                $u = rtrim($u, '/');
                return $u;
            }
        );
    }

    /** Uppercase 2 huruf untuk kode negara billing (ISO2) */
    protected function billingCountry(): Attribute
    {
        return Attribute::make(
            set: fn ($v) => is_string($v) ? strtoupper(trim($v)) : $v
        );
    }

    /** Uppercase 2 huruf untuk kode negara shipping (ISO2) */
    protected function shippingCountry(): Attribute
    {
        return Attribute::make(
            set: fn ($v) => is_string($v) ? strtoupper(trim($v)) : $v
        );
    }

    /* =========================
     * ACCESSORS/HELPERS (alamat inline)
     * ========================= */

    /** Billing address 1 baris: "street, city, state, zip" */
    public function getBillingAddressInlineAttribute(): string
    {
        return collect([
            $this->billing_street,
            $this->billing_city,
            $this->billing_state,
            $this->billing_zip,
        ])->filter()->implode(', ');
    }

    public function setNpwpNumberAttribute($value): void
    {
        $digits = preg_replace('/\D+/', '', (string) $value);
        $this->attributes['npwp_number'] = $digits ?: null;
    }

    public function getNpwpNumberPrettyAttribute(): ?string
    {
        $d = $this->npwp_number;
        if (!$d) return null;
        return $d;
    }

    /** Shipping address 1 baris: "street, city, state, zip" */
    public function getShippingAddressInlineAttribute(): string
    {
        return collect([
            $this->shipping_street,
            $this->shipping_city,
            $this->shipping_state,
            $this->shipping_zip,
        ])->filter()->implode(', ');
    }
}
