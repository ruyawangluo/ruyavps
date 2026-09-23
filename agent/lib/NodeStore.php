<?php
namespace Agent;

/**
 * 节点本地实例数据访问层（基于 SQLite）。
 */
class NodeStore
{
    public function instances(): array
    {
        return Db::all('SELECT * FROM instances ORDER BY rowid DESC');
    }

    public function get(string $incusName): ?array
    {
        return Db::one('SELECT * FROM instances WHERE incus_name = ?', [$incusName]);
    }

    public function findByKey(string $accessKey): ?array
    {
        return Db::one('SELECT * FROM instances WHERE access_key = ?', [$accessKey]);
    }

    public function save(array $d): void
    {
        $now = date('Y-m-d H:i:s');
        $d['updated_at'] = $now;
        $exists = $this->get((string)$d['incus_name']);
        if ($exists) {
            $sets = [];
            $params = [];
            foreach ($d as $k => $v) {
                if ($k === 'incus_name') {
                    continue;
                }
                $sets[] = "`$k` = ?";
                $params[] = $v;
            }
            $params[] = $d['incus_name'];
            Db::exec('UPDATE instances SET ' . implode(',', $sets) . ' WHERE incus_name = ?', $params);
            return;
        }
        $d['created_at'] = $d['created_at'] ?? $now;
        $cols = array_keys($d);
        Db::exec(
            'INSERT INTO instances (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')',
            array_values($d)
        );
    }

    public function setStatus(string $incusName, string $status): void
    {
        Db::exec('UPDATE instances SET status = ?, updated_at = ? WHERE incus_name = ?', [$status, date('Y-m-d H:i:s'), $incusName]);
    }

    public function remove(string $incusName): void
    {
        Db::exec('DELETE FROM instances WHERE incus_name = ?', [$incusName]);
    }

    public function addEvent(string $incusName, string $action, $result = null): void
    {
        Db::exec('INSERT INTO events (incus_name, action, result, created_at) VALUES (?,?,?,?)', [
            $incusName, $action, is_string($result) ? $result : json_encode($result, JSON_UNESCAPED_UNICODE), date('Y-m-d H:i:s'),
        ]);
    }

    public function events(string $incusName, int $limit = 20): array
    {
        return Db::all('SELECT * FROM events WHERE incus_name = ? ORDER BY id DESC LIMIT ' . max(1, $limit), [$incusName]);
    }

    public function setting(string $k, $default = null)
    {
        $row = Db::one('SELECT v FROM settings WHERE k = ?', [$k]);
        return $row === null ? $default : $row['v'];
    }

    public function setSetting(string $k, string $v): void
    {
        Db::exec('INSERT INTO settings (k,v) VALUES (?,?) ON CONFLICT(k) DO UPDATE SET v = excluded.v', [$k, $v]);
    }

    /** 用 incus 实际状态刷新本地记录。 */
    public function refreshStatuses(array $live): void
    {
        foreach ($this->instances() as $row) {
            $name = $row['incus_name'];
            if (!isset($live[$name])) {
                continue;
            }
            $st = strtolower((string)($live[$name]['status'] ?? ''));
            if ($st !== '' && $st !== strtolower((string)$row['status'])) {
                $this->setStatus($name, $st);
            }
            $ip = $live[$name]['ip'] ?? '';
            if ($ip !== '' && $ip !== $row['ip']) {
                Db::exec('UPDATE instances SET ip = ?, updated_at = ? WHERE incus_name = ?', [$ip, date('Y-m-d H:i:s'), $name]);
            }
        }
    }
}
