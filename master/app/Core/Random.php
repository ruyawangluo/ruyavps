<?php
namespace App\Core;

class Random
{
    public static function key(int $bytes = 24): string
    {
        return bin2hex(random_bytes($bytes));
    }

    public static function password(int $length = 16): string
    {
        $chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $max = strlen($chars) - 1;
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $chars[random_int(0, $max)];
        }
        return $out;
    }
}
