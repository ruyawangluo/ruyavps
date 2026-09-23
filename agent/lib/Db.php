<?php
namespace Agent;

/**
 * 节点本地数据库（SQLite）。节点自治：实例记录、事件、设置都存在本地，
 * 不依赖面板或任何外部数据库。
 */
class Db
{
    private static ?\PDO $pdo = null;

    public static function connect(string $file): \PDO
    {
        if (self::$pdo === null) {
            $dir = dirname($file);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            self::$pdo = new \PDO('sqlite:' . $file, null, null, [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);
            self::$pdo->exec('PRAGMA journal_mode = WAL');
            self::$pdo->exec('PRAGMA busy_timeout = 5000');
            self::migrate();
        }
        return self::$pdo;
    }

    private static function migrate(): void
    {
        self::$pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS instances (
  incus_name   TEXT PRIMARY KEY,
  name         TEXT NOT NULL DEFAULT '',
  access_key   TEXT NOT NULL UNIQUE,
  owner_email  TEXT NOT NULL DEFAULT '',
  variant      TEXT NOT NULL DEFAULT 'container',
  cpu          INTEGER NOT NULL DEFAULT 1,
  memory_mb    INTEGER NOT NULL DEFAULT 512,
  disk_gb      INTEGER NOT NULL DEFAULT 15,
  storage_pool TEXT NOT NULL DEFAULT '',
  ip           TEXT NOT NULL DEFAULT '',
  ip_mode      TEXT NOT NULL DEFAULT 'static',
  os_user      TEXT NOT NULL DEFAULT 'root',
  root_password TEXT NOT NULL DEFAULT '',
  status       TEXT NOT NULL DEFAULT 'creating',
  extra        TEXT,
  created_at   TEXT NOT NULL,
  updated_at   TEXT
);
CREATE TABLE IF NOT EXISTS events (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  incus_name TEXT NOT NULL DEFAULT '',
  action     TEXT NOT NULL DEFAULT '',
  result     TEXT,
  created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS settings (
  k TEXT PRIMARY KEY,
  v TEXT
);
SQL);
    }

    private static function pdo(): \PDO
    {
        if (self::$pdo === null) {
            throw new \RuntimeException('数据库未初始化');
        }
        return self::$pdo;
    }

    public static function all(string $sql, array $params = []): array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    public static function exec(string $sql, array $params = []): void
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
    }
}
