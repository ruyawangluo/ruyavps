<?php
namespace App\Core;

/**
 * settings 表读写（进程内缓存）。
 */
class Setting
{
    private static ?array $cache = null;

    public static function load(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            try {
                foreach (Db::all('SELECT `k`, `v` FROM settings') as $row) {
                    self::$cache[$row['k']] = $row['v'];
                }
            } catch (\Throwable $e) {
                self::$cache = [];
            }
        }
        return self::$cache;
    }

    public static function get(string $key, $default = null)
    {
        $all = self::load();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function set(string $key, string $value): void
    {
        $exists = Db::value('SELECT `k` FROM settings WHERE `k` = ?', [$key]);
        if ($exists) {
            Db::update('settings', ['v' => $value], '`k` = :k', ['k' => $key]);
        } else {
            Db::insert('settings', ['k' => $key, 'v' => $value]);
        }
        self::load();
        self::$cache[$key] = $value;
    }
}
