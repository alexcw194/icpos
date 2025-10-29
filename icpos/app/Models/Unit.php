<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    protected function code(): Attribute
    {
        return Attribute::make(
            set: fn ($v) => is_string($v) ? strtolower(trim($v)) : $v
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('code');
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeKeyword($query, ?string $q)
    {
        $q = trim((string) $q);
        if ($q === '') return $query;

        return $query->where(function ($w) use ($q) {
            $w->where('code', 'like', "%{$q}%")
              ->orWhere('name', 'like', "%{$q}%");
        });
    }

    protected static function booted(): void
    {
        static::deleting(function (Unit $unit) {
            if ($unit->items()->exists()) {
                throw new \RuntimeException('Unit tidak bisa dihapus karena sudah dipakai Item.');
            }
        });
    }
}
