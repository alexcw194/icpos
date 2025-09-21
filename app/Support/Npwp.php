<?php

namespace App\Support;

class Npwp
{
    public static function normalize(?string $v): string
    {
        return preg_replace('/\D+/', '', (string) $v);
    }

    public static function valid(?string $v): bool
    {
        $d = self::normalize($v);
        return in_array(strlen($d), [15,16], true); // lama 15, baru 16
    }
}
