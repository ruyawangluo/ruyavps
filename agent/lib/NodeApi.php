<?php
namespace Agent;

/**
 * 节点本地控制 API。
 *
 * 节点自治：被控程序 + 本地 SQLite 库 + Incus。面板/财务系统作为客户端，
 * 用 {ip, 端口, key} 调用本接口远程控制本节点；不依赖面板也能独立管理。
 *
 * 鉴权：请求头 X-Node-Token 必须等于配置中的 node_key。
 */
class NodeApi
{
    private Incus $incus;
    private NodeStore $store;
    private array $config;

    public function __construct(Incus $incus, NodeStore $store, array $config)
    {
        $this->incus = $incus;
        $this->store = $store;
        $this->config = $config;
    }

    public function handle(string $method, string $path, array $headers, string $rawBody): void
    {
        $method = strtoupper($method);
        $path = '/' . trim($path, '/');

        if ($path === '/health' || $path === '/') {
            $this->out(200, [
                'ok'            => true,
                'agent_version' => Version::VERSION,
                'incus_version' => $this->incus->version(),
                'node'          => $this->config['node_name'] ?? gethostname(),
                'time'          => date('Y-m-d H:i:s'),
            ]);
            return;
        }

        if (!$this->authorized($headers)) {
            $this->out(401, ['code' => 401, 'message' => '无效的节点密钥']);
            return;
        }

        $body = json_decode($rawBody, true) ?: [];

        try {
            if ($method === 'GET' && $path === '/node') {
                $this->nodeInfo(); return;
            }
            if ($method === 'GET' && $path === '/instances') {
                $this->listInstances(); return;
            }
            if ($method === 'POST' && $path === '/instances') {
                $this->createInstance($body); return;
            }
            if ($method === 'GET' && $path === '/images') {
                $this->listImages(); return;
            }
            if ($method === 'GET' && $path === '/storage') {
                $this->listStorage(); return;
            }
            if (preg_match('#^/instances/([^/]+)$#', $path, $m)) {
                $name = urldecode($m[1]);
                if ($method === 'GET') {
                    $this->getInstance($name); return;
                }
                if ($method === 'DELETE') {
                    $this->deleteInstance($name); return;
                }
            }
            if (preg_match('#^/instances/([^/]+)/action$#', $path, $m) && $method === 'POST') {
                $this->action(urldecode($m[1]), $body); return;
            }
            $this->out(404, ['code' => 404, 'message' => '接口不存在']);
        } catch (\Throwable $e) {
            $this->out(400, ['code' => 400, 'message' => $e->getMessage()]);
        }
    }

    private function authorized(array $headers): bool
    {
        $key = (string)($this->config['node_key'] ?? '');
        if ($key === '') {
            return false;
        }
        return hash_equals($key, (string)($headers['x-node-token'] ?? ''));
    }

    /** 节点自身接入信息：{ip, 端口, key}。 */
    private function nodeInfo(): void
    {
        $ip = (string)($this->config['node_ip'] ?? '');
        if ($ip === '') {
            $ip = trim((string)@shell_exec("hostname -I 2>/dev/null | awk '{print \$1}'"));
        }
        $this->out(200, ['code' => 0, 'data' => [
            'name' => (string)($this->config['node_name'] ?? gethostname()),
            'ip'   => $ip,
            'port' => (int)($this->config['api_port'] ?? 8787),
            'key'  => (string)($this->config['node_key'] ?? ''),
            'incus_version' => $this->incus->version(),
            'agent_version' => Version::VERSION,
        ]]);
    }

    private function listInstances(): void
    {
        $live = $this->safeInstances();
        if ($live !== null) {
            $this->store->refreshStatuses($live);
        }
        $rows = $this->store->instances();
        foreach ($rows as &$r) {
            $name = $r['incus_name'];
            if ($live !== null && isset($live[$name])) {
                $r['live_status'] = strtolower($live[$name]['status'] ?? '');
                $r['cpu_usage'] = $live[$name]['cpu_pct'] ?? 0;
                $r['mem_usage_mb'] = $live[$name]['mem_mb'] ?? 0;
            }
        }
        unset($r);
        $this->out(200, ['code' => 0, 'data' => ['instances' => $rows]]);
    }

    private function getInstance(string $name): void
    {
        $row = $this->store->get($name);
        if (!$row) {
            $this->out(404, ['code' => 404, 'message' => '实例不存在']);
            return;
        }
        $live = $this->safeInstances();
        if ($live !== null && isset($live[$name])) {
            $row['status'] = strtolower($live[$name]['status'] ?? $row['status']);
            $row['ip'] = $live[$name]['ip'] ?: $row['ip'];
            $row['cpu_usage'] = $live[$name]['cpu_pct'] ?? 0;
            $row['mem_usage_mb'] = $live[$name]['mem_mb'] ?? 0;
        }
        $row['events'] = $this->store->events($name, 20);
        $this->out(200, ['code' => 0, 'data' => $row]);
    }

    private function createInstance(array $p): void
    {
        if (empty($p['source'])) {
            throw new \RuntimeException('缺少镜像 source（如 local:ubuntu2404-lite-amd64-lxc 或 images:ubuntu/24.04）');
        }
        $name = trim((string)($p['incus_name'] ?? '')) ?: ('c-' . bin2hex(random_bytes(4)));
        if ($this->incus->exists($name)) {
            throw new \RuntimeException("实例 {$name} 已存在");
        }
        $accessKey = trim((string)($p['access_key'] ?? '')) ?: ('ak_' . bin2hex(random_bytes(16)));

        $spec = [
            'incus_name'     => $name,
            'source'         => (string)$p['source'],
            'variant'        => (string)($p['variant'] ?? 'container'),
            'storage_pool'   => (string)($p['storage_pool'] ?? ($this->config['storage_pool'] ?? 'default')),
            'cpu'            => (int)($p['cpu'] ?? 1),
            'memory_mb'      => (int)($p['memory_mb'] ?? 512),
            'disk_gb'        => (int)($p['disk_gb'] ?? 15),
            'bridge'         => (string)($p['bridge'] ?? ($this->config['bridge'] ?? 'incusbr0')),
            'ip'             => (string)($p['ip'] ?? ''),
            'ip_mode'        => (string)($p['ip_mode'] ?? 'static'),
            'gateway'        => (string)($p['gateway'] ?? ''),
            'netmask'        => (int)($p['netmask'] ?? 24),
            'hostname'       => (string)($p['hostname'] ?? ''),
            'root_password'  => (string)($p['root_password'] ?? ''),
            'os_user'        => (string)($p['os_user'] ?? 'root'),
            'cloud_init'     => (int)($p['cloud_init'] ?? 0),
            'user_data'      => (string)($p['user_data'] ?? ''),
            'config'         => is_array($p['config'] ?? null) ? $p['config'] : [],
            'inbound_mbps'   => (int)($p['inbound_mbps'] ?? 0),
            'outbound_mbps'  => (int)($p['outbound_mbps'] ?? 0),
            'disk_read_mbs'  => (int)($p['disk_read_mbs'] ?? 0),
            'disk_write_mbs' => (int)($p['disk_write_mbs'] ?? 0),
            'cpu_allowance'  => (int)($p['cpu_allowance'] ?? 100),
            'processes'      => (int)($p['processes'] ?? 0),
            'nesting'        => (int)($p['nesting'] ?? 0),
            'privileged'     => (int)($p['privileged'] ?? 0),
            'swap'           => (int)($p['swap'] ?? 1),
        ];

        $result = $this->incus->create($spec);

        $this->store->save([
            'incus_name'   => $name,
            'name'         => (string)($p['name'] ?? $name),
            'access_key'   => $accessKey,
            'owner_email'  => (string)($p['owner_email'] ?? ''),
            'variant'      => $spec['variant'],
            'cpu'          => $spec['cpu'],
            'memory_mb'    => $spec['memory_mb'],
            'disk_gb'      => $spec['disk_gb'],
            'storage_pool' => $spec['storage_pool'],
            'ip'           => $result['ip'] ?? $spec['ip'],
            'ip_mode'      => $spec['ip_mode'],
            'os_user'      => $spec['os_user'],
            'root_password'=> $spec['root_password'],
            'status'       => 'running',
            'extra'        => json_encode(['inbound_mbps'=>$spec['inbound_mbps'],'outbound_mbps'=>$spec['outbound_mbps']], JSON_UNESCAPED_UNICODE),
        ]);
        $this->store->addEvent($name, 'create', ['ip' => $result['ip'] ?? '']);

        $this->out(200, ['code' => 0, 'message' => '创建成功', 'data' => [
            'incus_name' => $name,
            'access_key' => $accessKey,
            'ip'         => $result['ip'] ?? $spec['ip'],
            'root_password' => $spec['root_password'],
        ]]);
    }

    private function deleteInstance(string $name): void
    {
        $this->incus->delete($name);
        $this->store->remove($name);
        $this->store->addEvent($name, 'delete');
        $this->out(200, ['code' => 0, 'message' => '已删除', 'data' => ['incus_name' => $name]]);
    }

    private function action(string $name, array $b): void
    {
        if (!$this->store->get($name) && !$this->incus->exists($name)) {
            $this->out(404, ['code' => 404, 'message' => '实例不存在']);
            return;
        }
        $action = (string)($b['action'] ?? '');
        $osUser = (string)($b['os_user'] ?? 'root');
        $status = null;

        switch ($action) {
            case 'start': case 'boot':
                $this->incus->start($name); $status = 'running'; break;
            case 'stop': case 'shutdown':
                $this->incus->stop($name); $status = 'stopped'; break;
            case 'reboot': case 'restart':
                $this->incus->restart($name); $status = 'running'; break;
            case 'reset_password':
                $pwd = (string)($b['password'] ?? '') ?: bin2hex(random_bytes(6));
                $this->incus->resetPassword($name, $osUser, $pwd);
                if ($row = $this->store->get($name)) { $row['root_password'] = $pwd; $this->store->save($row); }
                break;
            case 'resize':
                $this->incus->resize($name, (int)($b['cpu'] ?? 0), (int)($b['memory_mb'] ?? 0), (int)($b['disk_gb'] ?? 0));
                break;
            case 'set_limits':
                $this->incus->setLimits($name, [
                    'inbound_mbps'   => $b['inbound_mbps'] ?? null,
                    'outbound_mbps'  => $b['outbound_mbps'] ?? null,
                    'disk_read_mbs'  => $b['disk_read_mbs'] ?? null,
                    'disk_write_mbs' => $b['disk_write_mbs'] ?? null,
                    'cpu_allowance'  => $b['cpu_allowance'] ?? null,
                    'processes'      => $b['processes'] ?? null,
                ]);
                break;
            case 'snapshot_create':
                $this->incus->snapshotCreate($name, (string)($b['snapshot'] ?? ('snap-' . date('YmdHis'))));
                break;
            case 'snapshot_restore':
                $this->incus->snapshotRestore($name, (string)($b['snapshot'] ?? ''));
                break;
            case 'snapshot_delete':
                $this->incus->snapshotDelete($name, (string)($b['snapshot'] ?? ''));
                break;
            case 'exec':
                $cmd = $b['command'] ?? '';
                if (is_array($cmd)) { $cmd = implode(' ', $cmd); }
                $out = $this->incus->execCommand($name, (string)$cmd);
                $this->store->addEvent($name, 'exec', $out);
                $this->out(200, ['code' => 0, 'message' => 'ok', 'data' => ['output' => $out]]);
                return;
            default:
                throw new \RuntimeException('不支持的 action: ' . $action);
        }

        if ($status !== null) {
            $this->store->setStatus($name, $status);
        }
        $this->store->addEvent($name, $action, ['status' => $status]);
        $this->out(200, ['code' => 0, 'message' => '操作成功', 'data' => ['incus_name' => $name, 'action' => $action, 'status' => $status]]);
    }

    private function listImages(): void
    {
        $r = $this->incus->run(['image', 'list', '--format', 'json']);
        if ($r['code'] !== 0) {
            $this->out(200, ['code' => 0, 'data' => ['images' => [], 'warning' => trim($r['err'] ?: $r['out'])]]);
            return;
        }
        $rows = json_decode($r['out'], true) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $aliases = array_map(fn($a) => $a['name'] ?? '', $row['aliases'] ?? []);
            $out[] = [
                'fingerprint' => substr((string)($row['fingerprint'] ?? ''), 0, 12),
                'aliases'     => $aliases,
                'name'        => $aliases[0] ?? substr((string)($row['fingerprint'] ?? ''), 0, 12),
                'architecture'=> $row['architecture'] ?? '',
                'type'        => strtolower((string)($row['type'] ?? '')),
                'size'        => $row['size'] ?? 0,
            ];
        }
        $this->out(200, ['code' => 0, 'data' => ['images' => $out]]);
    }

    private function listStorage(): void
    {
        $pools = $this->incus->storagePools();
        $this->out(200, ['code' => 0, 'data' => ['storage_pools' => $pools]]);
    }

    /** 读取 incus 实例列表；失败返回 null（不抛错，便于节点无 incus 时也能响应）。 */
    private function safeInstances(): ?array
    {
        try {
            return $this->incus->listInstances();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function out(int $status, array $data): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
