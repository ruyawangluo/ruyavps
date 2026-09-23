<?php
namespace App\Controllers;

use App\Core\Db;
use App\Core\Http;
use App\Core\InstanceAuth;
use App\Core\Log;
use App\Services\InstanceService;
use App\Services\NodeService;

/**
 * 单机 API：凭「每台机器的访问 Key」直接控制该机器。
 * 面板据 Key 找到索引记录 -> 转发到对应节点。
 */
class InstanceApiController
{
    public function handle(): void
    {
        try {
            $inst = InstanceAuth::authenticate();
        } catch (\RuntimeException $e) {
            Http::jsonResponse(['code' => $e->getCode() ?: 401, 'message' => $e->getMessage(), 'data' => null], $e->getCode() ?: 401);
        }

        try {
            switch (Http::path()) {
                case '/api/v1/instance/info':   $this->info($inst); return;
                case '/api/v1/instance/action': $this->action($inst); return;
            }
            Http::jsonResponse(['code' => 404, 'message' => '接口不存在', 'data' => null], 404);
        } catch (\Throwable $e) {
            Http::jsonResponse(['code' => 500, 'message' => '服务异常：' . $e->getMessage(), 'data' => null], 500);
        }
    }

    private function info(array $inst): void
    {
        $node = NodeService::find((int)$inst['node_id']);
        $live = null;
        if ($node) {
            $res = NodeService::client($node)->getInstance((string)$inst['incus_name']);
            $live = $res['ok'] ? $res['data'] : null;
        }
        Http::jsonResponse(['code' => 0, 'message' => 'ok', 'data' => [
            'instance_id'   => (int)$inst['id'],
            'node_id'       => (int)$inst['node_id'],
            'node_ip'       => $node['ip'] ?? '',
            'node_port'     => (int)($node['port'] ?? 0),
            'name'          => $inst['name'],
            'incus_name'    => $inst['incus_name'],
            'access_key'    => $inst['access_key'],
            'status'        => $live['status'] ?? $inst['status'],
            'variant'       => $inst['variant'],
            'cpu'           => (int)$inst['cpu'],
            'memory_mb'     => (int)$inst['memory_mb'],
            'disk_gb'       => (int)$inst['disk_gb'],
            'ip'            => $live['ip'] ?? $inst['ip'],
            'os_user'       => $live['os_user'] ?? 'root',
            'root_password' => $live['root_password'] ?? '',
            'cpu_usage'     => (float)($live['cpu_usage'] ?? $inst['cpu_usage']),
            'mem_usage_mb'  => (int)($live['mem_usage_mb'] ?? $inst['mem_usage_mb']),
            'events'        => $live['events'] ?? [],
        ]]);
    }

    private function action(array $inst): void
    {
        $action = (string)Http::input('action', '');
        if ($action === '') {
            Http::jsonResponse(['code' => 400, 'message' => '缺少 action', 'data' => null], 400);
        }
        $payload = [];
        foreach (['password', 'cpu', 'memory_mb', 'disk_gb', 'snapshot', 'command', 'inbound_mbps', 'outbound_mbps', 'disk_read_mbs', 'disk_write_mbs', 'cpu_allowance', 'processes', 'source', 'variant', 'root_password'] as $f) {
            $v = Http::input($f, null);
            if ($v !== null && $v !== '') {
                $payload[$f] = $v;
            }
        }
        $res = InstanceService::action((int)$inst['id'], $action, $payload);
        if (!$res['ok']) {
            Http::jsonResponse(['code' => 400, 'message' => $res['error'], 'data' => null], 400);
        }
        Log::write('api', 0, 'instance.' . $action, '#' . $inst['id']);
        Http::jsonResponse(['code' => 0, 'message' => '操作成功', 'data' => [
            'instance_id' => (int)$inst['id'], 'action' => $action, 'output' => $res['data']['output'] ?? null,
        ]]);
    }
}
