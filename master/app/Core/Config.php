<?php
namespace App\Core;

class Config
{
    public static function get(string $key, $default = null)
    {
        $cfg = $GLOBALS['__config'] ?? [];
        if (array_key_exists($key, $cfg)) {
            return $cfg[$key];
        }
        // 点号路径 a.b.c
        $node = $cfg;
        foreach (explode('.', $key) as $seg) {
            if (is_array($node) && array_key_exists($seg, $node)) {
                $node = $node[$seg];
            } else {
                return $default;
            }
        }
        return $node;
    }

    public static function all(): array
    {
        return $GLOBALS['__config'] ?? [];
    }
}
