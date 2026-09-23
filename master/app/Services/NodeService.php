<?php
namespace App\Services;

use App\Core\Config;
use App\Core\Db;
use App\Core\NodeClient;

/**
 * 面板侧节点注册表：只保存 {ip, 端口, key} 等接入信息与状态缓存。
 */
class NodeService
{
    public static function all(): array
    {
        return Db::all('SELECT * FROM nodes ORDER BY id ASC');
    }

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM nodes WHERE id = ?', [$id]);
    }

    /** 带进程内缓存的节点查询。 */
    public static function find_with_cache(int $id): ?array
    {
        static $cache = [];
        if (!array_key_exists($id, $cache)) {
            $cache[$id] = self::find($id);
        }
        return $cache[$id];
    }

    public static function create(array $d): int
    {
        return Db::insert('nodes', [
            'name'       => trim((string)($d['name'] ?? '')) ?: (string)($d['ip'] ?? 'node'),
            'ip'         => trim((string)($d['ip'] ?? '')),
            'port'       => (int)($d['port'] ?? 8787),
            'node_key'   => (string)($d['node_key'] ?? ''),
            'region'     => trim((string)($d['region'] ?? '默认区域')),
            'enabled'    => (int)($d['enabled'] ?? 1),
            'remark'     => (string)($d['remark'] ?? ''),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function client(array $node): NodeClient
    {
        return new NodeClient((string)$node['ip'], (int)$node['port'], (string)$node['node_key']);
    }

    /** 探测节点在线状态并刷新缓存。 */
    public static function refresh(array $node): array
    {
        $client = self::client($node);
        $info = $client->nodeInfo();
        if ($info['ok']) {
            $d = $info['data'] ?? [];
            Db::update('nodes', [
                'status'        => 1,
                'last_seen'     => date('Y-m-d H:i:s'),
                'incus_version' => (string)($d['incus_version'] ?? ''),
                'agent_version' => (string)($d['agent_version'] ?? ''),
            ], 'id = :id', ['id' => $node['id']]);
            return ['ok' => true, 'data' => $d];
        }
        Db::update('nodes', ['status' => 0], 'id = :id', ['id' => $node['id']]);
        return ['ok' => false, 'error' => $info['error']];
    }

    public static function refreshAll(): void
    {
        foreach (self::all() as $n) {
            self::refresh($n);
        }
    }

    public static function pickSchedulable(): ?array
    {
        return Db::one('SELECT * FROM nodes WHERE enabled = 1 ORDER BY (status = 1) DESC, id ASC LIMIT 1');
    }

    public static function stats(): array
    {
        return [
            'total'  => (int)Db::value('SELECT COUNT(*) FROM nodes'),
            'online' => (int)Db::value('SELECT COUNT(*) FROM nodes WHERE status = 1'),
        ];
    }
}
