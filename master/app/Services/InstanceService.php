<?php
namespace App\Services;

use App\Core\Db;
use App\Core\NodeClient;
use App\Core\Random;

/**
 * 面板侧实例索引 + 通过节点 API 远程控制。
 * 真实实例数据在节点端；本服务只维护「哪台机器在哪个节点、归属、访问 Key」与状态缓存。
 */
class InstanceService
{
    public static function uniqueIncusName(): string
    {
        do {
            $name = 'c-' . Random::key(4);
            $exists = Db::value('SELECT id FROM instances WHERE incus_name = ?', [$name]);
        } while ($exists);
        return $name;
    }

    public static function uniqueAccessKey(): string
    {
        do {
            $key = 'ak_' . bin2hex(random_bytes(16));
            $exists = Db::value('SELECT id FROM instances WHERE access_key = ?', [$key]);
        } while ($exists);
        return $key;
    }

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM instances WHERE id = ?', [$id]);
    }

    public static function findByAccessKey(string $key): ?array
    {
        return Db::one('SELECT * FROM instances WHERE access_key = ?', [$key]);
    }

    /** 解析 "k=v" 文本为数组。 */
    public static function parseConfigText(string $text): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $k = trim(substr($line, 0, $pos));
            $v = trim(substr($line, $pos + 1));
            if ($k !== '') {
                $out[$k] = $v;
            }
        }
        return $out;
    }

    public static function all(int $limit = 500): array
    {
        return Db::all("SELECT i.*, n.name AS node_name, n.ip AS node_ip, n.port AS node_port, u.email AS user_email
                          FROM instances i
                          LEFT JOIN nodes n ON n.id = i.node_id
                          LEFT JOIN users u ON u.id = i.user_id
                         WHERE i.status <> 'deleted'
                         ORDER BY i.id DESC LIMIT " . (int)$limit);
    }

    /**
     * 开通：面板生成 incus_name/access_key，调用节点 API 创建，落到索引。
     * @return array 索引记录
     */
    public static function create(int $userId, array $data): array
    {
        $node = !empty($data['node_id']) ? NodeService::find((int)$data['node_id']) : NodeService::pickSchedulable();
        if (!$node) {
            throw new \RuntimeException('没有可用的计算节点');
        }
        if (empty($data['source'])) {
            throw new \RuntimeException('请选择镜像（source 不能为空）');
        }

        $incusName = self::uniqueIncusName();
        $accessKey = self::uniqueAccessKey();

        $id = Db::insert('instances', [
            'node_id'    => (int)$node['id'],
            'user_id'    => $userId,
            'incus_name' => $incusName,
            'access_key' => $accessKey,
            'name'       => (string)($data['name'] ?? '') ?: $incusName,
            'variant'    => (string)($data['variant'] ?? 'container'),
            'cpu'        => max(1, (int)($data['cpu'] ?? 1)),
            'memory_mb'  => max(128, (int)($data['memory_mb'] ?? 512)),
            'disk_gb'    => max(1, (int)($data['disk_gb'] ?? 15)),
            'status'     => 'creating',
            'remark'     => (string)($data['remark'] ?? ''),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $payload = array_merge($data, [
            'incus_name'   => $incusName,
            'access_key'   => $accessKey,
            'owner_email'  => (string)($data['owner_email'] ?? ''),
        ]);
        unset($payload['node_id']);

        $res = NodeService::client($node)->createInstance($payload);
        if (!$res['ok']) {
            self::updateStatus($id, 'error');
            throw new \RuntimeException('节点创建失败：' . $res['error']);
        }
        $d = $res['data'] ?? [];
        Db::update('instances', [
            'status'     => 'running',
            'ip'         => (string)($d['ip'] ?? ''),
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $id]);

        return self::find($id) ?? [];
    }

    /** 对实例执行动作（经节点 API）。 */
    public static function action(int $id, string $type, array $payload = []): array
    {
        $inst = self::find($id);
        if (!$inst) {
            throw new \RuntimeException('实例不存在');
        }
        $node = NodeService::find((int)$inst['node_id']);
        if (!$node) {
            throw new \RuntimeException('实例所属节点不存在');
        }

        $body = array_merge(['action' => $type], $payload);
        $client = NodeService::client($node);

        if ($type === 'delete') {
            $res = $client->deleteInstance((string)$inst['incus_name']);
            if ($res['ok']) {
                self::updateStatus($id, 'deleted');
            }
            return $res;
        }

        $res = $client->action((string)$inst['incus_name'], $body);
        if ($res['ok']) {
            $map = ['start' => 'running', 'stop' => 'stopped', 'reboot' => 'running'];
            if (isset($map[$type])) {
                self::updateStatus($id, $map[$type]);
            }
        }
        return $res;
    }

    public static function updateStatus(int $id, string $status): void
    {
        Db::update('instances', ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);
    }

    /** 从各节点拉取实例列表刷新索引状态/用量/IP。 */
    public static function refreshAll(): void
    {
        $nodes = Db::all("SELECT DISTINCT node_id FROM instances WHERE status <> 'deleted'");
        $nodeIds = array_column($nodes, 'node_id');
        foreach ($nodeIds as $nid) {
            $node = NodeService::find((int)$nid);
            if (!$node) {
                continue;
            }
            $res = NodeService::client($node)->listInstances();
            if (!$res['ok']) {
                continue;
            }
            $byName = [];
            foreach (($res['data']['instances'] ?? []) as $it) {
                $byName[$it['incus_name']] = $it;
            }
            foreach (Db::all('SELECT id, incus_name FROM instances WHERE node_id = ?', [$node['id']]) as $row) {
                $it = $byName[$row['incus_name']] ?? null;
                if (!$it) {
                    continue;
                }
                Db::update('instances', [
                    'status'       => strtolower((string)($it['live_status'] ?? $it['status'] ?? '')),
                    'ip'           => (string)($it['ip'] ?? ''),
                    'cpu_usage'    => (float)($it['cpu_usage'] ?? 0),
                    'mem_usage_mb' => (int)($it['mem_usage_mb'] ?? 0),
                    'updated_at'   => date('Y-m-d H:i:s'),
                ], 'id = :id', ['id' => $row['id']]);
            }
        }
    }

    public static function stats(): array
    {
        return [
            'total'      => (int)Db::value("SELECT COUNT(*) FROM instances WHERE status <> 'deleted'"),
            'running'    => (int)Db::value("SELECT COUNT(*) FROM instances WHERE status = 'running'"),
            'stopped'    => (int)Db::value("SELECT COUNT(*) FROM instances WHERE status = 'stopped'"),
            'error'      => (int)Db::value("SELECT COUNT(*) FROM instances WHERE status = 'error'"),
            'containers' => (int)Db::value("SELECT COUNT(*) FROM instances WHERE variant = 'container' AND status <> 'deleted'"),
            'vms'        => (int)Db::value("SELECT COUNT(*) FROM instances WHERE variant = 'vm' AND status <> 'deleted'"),
        ];
    }
}
