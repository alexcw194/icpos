<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];
    public $timestamps = true;

    /**
     * Ambil nilai setting dari cache/DB.
     */
    public static function get(string $key, $default = null)
    {
        return Cache::remember("setting:$key", 300, function () use ($key, $default) {
            $row = static::query()->where('key', $key)->first();
            return $row?->value ?? $default;
        });
    }

    /**
     * Set banyak pasangan key=>value sekaligus.
     */
    public static function setMany(array $pairs): void
    {
        foreach ($pairs as $k => $v) {
            static::query()->updateOrCreate(['key' => $k], ['value' => $v]);
            Cache::forget("setting:$k");
        }
    }

    /**
     * Ambil semua setting sebagai array [key => value].
     */
    public static function allKeyed(): array
    {
        return static::query()->pluck('value', 'key')->toArray();
    }
}
