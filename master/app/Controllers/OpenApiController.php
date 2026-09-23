<?php
namespace App\Controllers;

use App\Core\Db;
use App\Core\Http;
use App\Core\Log;
use App\Core\OpenAuth;
use App\Core\Setting;
use App\Services\InstanceService;
use App\Services\NodeService;

/**
 * 开放 API：供上层财务/前台系统调用。
 * 平台级 HMAC 鉴权（settings.openapi_key / openapi_secret）。
 */
class OpenApiController
{
    public function handle(): void
    {
        try {
            OpenAuth::authenticate();
        } catch (\RuntimeException $e) {
            Http::jsonResponse(['code' => $e->getCode() ?: 401, 'message' => $e->getMessage(), 'data' => null], $e->getCode() ?: 401);
        }

        $path = Http::path();
        try {
            switch ($path) {
                case '/api/v1/open/ping':            $this->ok(['time' => date('Y-m-d H:i:s')]); return;
                case '/api/v1/open/user/create':     $this->userCreate(); return;
                case '/api/v1/open/user/get':        $this->userGet(); return;
                case '/api/v1/open/user/list':       $this->userList(); return;
                case '/api/v1/open/node/list':       $this->nodeList(); return;
                case '/api/v1/open/image/list':      $this->imageList(); return;
                case '/api/v1/open/instance/create': $this->instanceCreate(); return;
                case '/api/v1/open/instance/get':    $this->instanceGet(); return;
                case '/api/v1/open/instance/list':   $this->instanceList(); return;
                case '/api/v1/open/instance/action': $this->instanceAction(); return;
                case '/api/v1/open/instance/delete': $this->instanceDelete(); return;
            }
            $this->fail('接口不存在', 404);
        } catch (\RuntimeException $e) {
            $this->fail($e->getMessage(), $e->getCode() ?: 400);
        } catch (\Throwable $e) {
            $this->fail('服务异常：' . $e->getMessage(), 500);
        }
    }

    private function userCreate(): void
    {
        $email = trim((string)Http::input('email', ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->fail('email 格式不正确');
        }
        $user = Db::one('SELECT * FROM users WHERE email = ?', [$email]);
        if ($user) {
            $this->ok($this->userPayload($user), '用户已存在');
            return;
        }
        $id = Db::insert('users', [
            'email'         => $email,
            'password_hash' => (string)Http::input('password', '') !== '' ? password_hash((string)Http::input('password'), PASSWORD_DEFAULT) : '',
            'nickname'      => (string)Http::input('nickname', ''),
            'remark'        => (string)Http::input('remark', ''),
            'status'        => 1,
            'api_key'       => 'uk_' . bin2hex(random_bytes(12)),
            'api_secret'    => bin2hex(random_bytes(24)),
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
        Log::write('api', 0, 'user.create', $email);
        $this->ok($this->userPayload(Db::one('SELECT * FROM users WHERE id = ?', [$id])));
    }

    private function userGet(): void
    {
        $user = $this->resolveUser();
        if (!$user) {
            $this->fail('用户不存在', 404);
        }
        $this->ok($this->userPayload($user));
    }

    private function userList(): void
    {
        $this->ok(['users' => array_map([$this, 'userPayload'], Db::all('SELECT * FROM users ORDER BY id DESC LIMIT 500'))]);
    }

    private function nodeList(): void
    {
        $nodes = Db::all('SELECT id, name, ip, port, region, status, enabled, incus_version, agent_version, last_seen FROM nodes ORDER BY id');
        $this->ok(['nodes' => $nodes]);
    }

    private function imageList(): void
    {
        $nodeId = (int)Http::input('node_id', 0);
        $node = $nodeId ? NodeService::find($nodeId) : NodeService::pickSchedulable();
        if (!$node) {
            $this->ok(['images' => []]);
            return;
        }
        $res = NodeService::client($node)->images();
        $this->ok(['node_id' => (int)$node['id'], 'images' => $res['data']['images'] ?? []]);
    }

    private function instanceCreate(): void
    {
        $userId = (int)Http::input('user_id', 0);
        if ($userId <= 0) {
            $email = trim((string)Http::input('email', ''));
            if ($email === '') {
                $this->fail('需提供 user_id 或 email');
            }
            $user = Db::one('SELECT * FROM users WHERE email = ?', [$email]);
            $userId = $user ? (int)$user['id'] : Db::insert('users', [
                'email' => $email, 'password_hash' => '', 'nickname' => '', 'status' => 1,
                'api_key' => 'uk_' . bin2hex(random_bytes(12)), 'api_secret' => bin2hex(random_bytes(24)), 'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $config = Http::input('config', []);
        $row = InstanceService::create($userId, [
            'name'           => (string)Http::input('name', ''),
            'node_id'        => (int)Http::input('node_id', 0),
            'source'         => (string)Http::input('source', ''),
            'variant'        => (string)Http::input('variant', 'container'),
            'cpu'            => (int)Http::input('cpu', 1),
            'memory_mb'      => (int)Http::input('memory_mb', 512),
            'disk_gb'        => (int)Http::input('disk_gb', 15),
            'storage_pool'   => (string)Http::input('storage_pool', ''),
            'ip_mode'        => (string)Http::input('ip_mode', 'static'),
            'ip'             => (string)Http::input('ip', ''),
            'hostname'       => (string)Http::input('hostname', ''),
            'root_password'  => (string)Http::input('root_password', ''),
            'user_data'      => (string)Http::input('user_data', ''),
            'config'         => is_array($config) ? $config : (string)$config,
            'inbound_mbps'   => (int)Http::input('inbound_mbps', 0),
            'outbound_mbps'  => (int)Http::input('outbound_mbps', 0),
            'disk_read_mbs'  => (int)Http::input('disk_read_mbs', 0),
            'disk_write_mbs' => (int)Http::input('disk_write_mbs', 0),
            'cpu_allowance'  => (int)Http::input('cpu_allowance', 100),
            'processes'      => (int)Http::input('processes', 0),
            'nesting'        => (int)Http::input('nesting', 0),
            'privileged'     => (int)Http::input('privileged', 0),
            'swap'           => (int)Http::input('swap', 1),
            'remark'         => (string)Http::input('remark', ''),
        ]);
        Log::write('api', 0, 'instance.create', '#' . $row['id']);
        $this->ok($this->instancePayload($row), '开通成功');
    }

    private function instanceGet(): void
    {
        $inst = $this->resolveInstance();
        if (!$inst) {
            $this->fail('实例不存在', 404);
        }
        $this->ok($this->instancePayload($inst));
    }

    private function instanceList(): void
    {
        $userId = (int)Http::input('user_id', 0);
        $email = trim((string)Http::input('email', ''));
        if ($userId <= 0 && $email !== '') {
            $userId = (int)Db::value('SELECT id FROM users WHERE email = ?', [$email]);
        }
        $rows = $userId > 0
            ? Db::all("SELECT * FROM instances WHERE user_id = ? AND status <> 'deleted' ORDER BY id DESC", [$userId])
            : Db::all("SELECT * FROM instances WHERE status <> 'deleted' ORDER BY id DESC LIMIT 500");
        $this->ok(['instances' => array_map([$this, 'instancePayload'], $rows)]);
    }

    private function instanceAction(): void
    {
        $inst = $this->resolveInstance();
        if (!$inst) {
            $this->fail('实例不存在', 404);
        }
        $action = (string)Http::input('action', '');
        $payload = [];
        foreach (['password', 'cpu', 'memory_mb', 'disk_gb', 'snapshot', 'command', 'inbound_mbps', 'outbound_mbps', 'disk_read_mbs', 'disk_write_mbs', 'cpu_allowance', 'processes', 'source', 'variant', 'root_password'] as $f) {
            $v = Http::input($f, null);
            if ($v !== null && $v !== '') {
                $payload[$f] = $v;
            }
        }
        $res = InstanceService::action((int)$inst['id'], $action, $payload);
        if (!$res['ok']) {
            $this->fail($res['error']);
        }
        Log::write('api', 0, 'instance.' . $action, '#' . $inst['id']);
        $this->ok(['instance_id' => (int)$inst['id'], 'action' => $action, 'output' => $res['data']['output'] ?? null], '操作成功');
    }

    private function instanceDelete(): void
    {
        $inst = $this->resolveInstance();
        if (!$inst) {
            $this->fail('实例不存在', 404);
        }
        $res = InstanceService::action((int)$inst['id'], 'delete');
        if (!$res['ok']) {
            $this->fail($res['error']);
        }
        $this->ok(['instance_id' => (int)$inst['id']], '已删除');
    }

    private function resolveUser(): ?array
    {
        $id = (int)Http::input('user_id', 0);
        if ($id > 0) {
            return Db::one('SELECT * FROM users WHERE id = ?', [$id]);
        }
        $email = trim((string)Http::input('email', ''));
        return $email !== '' ? Db::one('SELECT * FROM users WHERE email = ?', [$email]) : null;
    }

    private function resolveInstance(): ?array
    {
        $id = (int)Http::input('instance_id', 0);
        if ($id > 0) {
            return InstanceService::find($id);
        }
        $key = trim((string)Http::input('access_key', ''));
        if ($key !== '') {
            return InstanceService::findByAccessKey($key);
        }
        $name = trim((string)Http::input('incus_name', ''));
        return $name !== '' ? Db::one('SELECT * FROM instances WHERE incus_name = ?', [$name]) : null;
    }

    private function userPayload(array $u): array
    {
        return ['user_id' => (int)$u['id'], 'email' => $u['email'], 'nickname' => $u['nickname'], 'status' => (int)$u['status'], 'api_key' => $u['api_key'], 'api_secret' => $u['api_secret'], 'created_at' => $u['created_at']];
    }

    private function instancePayload(array $i): array
    {
        $node = NodeService::find_with_cache((int)$i['node_id']);
        return [
            'instance_id' => (int)$i['id'],
            'node_id'     => (int)$i['node_id'],
            'node_ip'     => $node['ip'] ?? '',
            'node_port'   => (int)($node['port'] ?? 0),
            'user_id'     => (int)$i['user_id'],
            'name'        => $i['name'],
            'incus_name'  => $i['incus_name'],
            'access_key'  => $i['access_key'],
            'status'      => $i['status'],
            'variant'     => $i['variant'],
            'cpu'         => (int)$i['cpu'],
            'memory_mb'   => (int)$i['memory_mb'],
            'disk_gb'     => (int)$i['disk_gb'],
            'ip'          => $i['ip'],
            'cpu_usage'   => (float)$i['cpu_usage'],
            'mem_usage_mb'=> (int)$i['mem_usage_mb'],
            'created_at'  => $i['created_at'],
        ];
    }

    private function ok($data, string $message = 'ok'): void
    {
        Http::jsonResponse(['code' => 0, 'message' => $message, 'data' => $data]);
    }

    private function fail(string $message, int $code = 400): void
    {
        Http::jsonResponse(['code' => $code, 'message' => $message, 'data' => null], $code < 400 ? 400 : $code);
    }
}
