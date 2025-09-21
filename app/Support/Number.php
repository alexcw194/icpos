<?php


namespace App\Support;


class Number
{
/** Ubah "1.234.567,89" -> 1234567.89 (format ID ke float) */
public static function idToFloat($val): float
{
if ($val === null) return 0.0;
$s = preg_replace('/[^\d,.\-]/', '', (string)$val);
$s = str_replace('.', '', $s); // hapus thousands sep
$s = str_replace(',', '.', $s); // koma -> titik
return (float) $s;
}
}