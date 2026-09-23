<?php
namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Db;
use App\Core\Http;
use App\Core\Log;
use App\Services\InstanceService;
use App\Services\NodeService;

class InstanceController extends Controller
{
    public function index(): void
    {
        $admin = Auth::requireAdmin();
        InstanceService::refreshAll();
        $this->view('admin/instances/index', [
            'title'     => '云服务器',
            'admin'     => $admin,
            'instances' => InstanceService::all(),
            'keyword'   => trim((string)Http::input('q', '')),
        ], 'admin/layout');
    }

    public function create(): void
    {
        $admin = Auth::requireAdmin();
        $this->view('admin/instances/form', [
            'title' => '开通云服务器',
            'admin' => $admin,
            'nodes' => Db::all('SELECT * FROM nodes WHERE enabled = 1 ORDER BY (status=1) DESC, id'),
            'users' => Db::all('SELECT id, email FROM users WHERE status = 1 ORDER BY id'),
        ], 'admin/layout');
    }

    /** 返回某节点的镜像列表（供表单动态加载）。 */
    public function imagesJson(): void
    {
        Auth::requireAdmin();
        $nodeId = (int)Http::input('node_id', 0);
        $node = $nodeId ? NodeService::find($nodeId) : NodeService::pickSchedulable();
        if (!$node) {
            Http::jsonResponse(['code' => 0, 'data' => ['images' => []], 'message' => '无可用节点']);
        }
        $res = NodeService::client($node)->images();
        Http::jsonResponse(['code' => 0, 'data' => ['images' => $res['data']['images'] ?? [], 'warning' => $res['data']['warning'] ?? ''], 'message' => $res['ok'] ? 'ok' : $res['error']]);
    }

    public function store(): void
    {
        $admin = Auth::requireAdmin();
        Csrf::check();
        try {
            $row = InstanceService::create((int)Http::input('user_id', 0), $this->collect());
            Log::write('admin', (int)$admin['id'], 'instance.create', '#' . ($row['id'] ?? ''));
            flash('success', '机器已开通。');
            $this->redirect('/admin/instances/' . (int)$row['id']);
        } catch (\Throwable $e) {
            flash('error', '开通失败：' . $e->getMessage());
            $this->redirect('/admin/instances/create');
        }
    }

    public function show(string $id): void
    {
        $admin = Auth::requireAdmin();
        $inst = Db::one(
            "SELECT i.*, n.name AS node_name, n.ip AS node_ip, n.port AS node_port, n.status AS node_status, u.email AS user_email
               FROM instances i LEFT JOIN nodes n ON n.id = i.node_id LEFT JOIN users u ON u.id = i.user_id
              WHERE i.id = ?",
            [(int)$id]
        );
        if (!$inst) {
            $this->abort404('实例不存在');
        }
        $live = null;
        $node = NodeService::find((int)$inst['node_id']);
        if ($node) {
            $res = NodeService::client($node)->getInstance((string)$inst['incus_name']);
            $live = $res['ok'] ? $res['data'] : null;
        }
        $this->view('admin/instances/show', [
            'title'    => '实例详情',
            'admin'    => $admin,
            'instance' => $inst,
            'live'     => $live,
        ], 'admin/layout');
    }

    public function action(string $id): void
    {
        $admin = Auth::requireAdmin();
        Csrf::check();
        $inst = InstanceService::find((int)$id);
        if (!$inst) {
            $this->abort404('实例不存在');
        }
        $type = (string)Http::input('type', '');
        $payload = [];
        foreach (['password', 'cpu', 'memory_mb', 'disk_gb', 'snapshot', 'command', 'inbound_mbps', 'outbound_mbps', 'disk_read_mbs', 'disk_write_mbs', 'cpu_allowance', 'processes', 'source', 'variant', 'root_password', 'image_source'] as $f) {
            $v = Http::input($f, null);
            if ($v !== null && $v !== '') {
                $payload[$f] = $v;
            }
        }
        try {
            $res = InstanceService::action((int)$id, $type, $payload);
            if (!$res['ok']) {
                flash('error', $res['error']);
            } else {
                Log::write('admin', (int)$admin['id'], 'instance.' . $type, '#' . $id);
                flash('success', $type === 'exec' ? ('输出：' . mb_substr((string)($res['data']['output'] ?? ''), 0, 400)) : '操作成功');
            }
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        $this->redirect('/admin/instances/' . $id);
    }

    public function destroy(string $id): void
    {
        Auth::requireAdmin();
        Csrf::check();
        try {
            InstanceService::action((int)$id, 'delete');
            flash('success', '已删除');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        $this->redirect('/admin/instances');
    }

    private function collect(): array
    {
        return [
            'name'           => trim((string)Http::input('name', '')),
            'node_id'        => (int)Http::input('node_id', 0),
            'source'         => trim((string)Http::input('source', '')),
            'variant'        => (string)Http::input('variant', 'container'),
            'cpu'            => (int)Http::input('cpu', 1),
            'memory_mb'      => (int)Http::input('memory_mb', 512),
            'disk_gb'        => (int)Http::input('disk_gb', 15),
            'storage_pool'   => trim((string)Http::input('storage_pool', '')),
            'ip_mode'        => (string)Http::input('ip_mode', 'static'),
            'ip'             => trim((string)Http::input('ip', '')),
            'hostname'       => trim((string)Http::input('hostname', '')),
            'root_password'  => (string)Http::input('root_password', ''),
            'os_user'        => trim((string)Http::input('os_user', '')),
            'inbound_mbps'   => (int)Http::input('inbound_mbps', 0),
            'outbound_mbps'  => (int)Http::input('outbound_mbps', 0),
            'disk_read_mbs'  => (int)Http::input('disk_read_mbs', 0),
            'disk_write_mbs' => (int)Http::input('disk_write_mbs', 0),
            'cpu_allowance'  => (int)Http::input('cpu_allowance', 100),
            'processes'      => (int)Http::input('processes', 0),
            'nesting'        => (int)Http::input('nesting', 0),
            'privileged'     => (int)Http::input('privileged', 0),
            'swap'           => (int)Http::input('swap', 1),
            'user_data'      => (string)Http::input('user_data', ''),
            'config'         => InstanceService::parseConfigText((string)Http::input('config_raw', '')),
            'remark'         => trim((string)Http::input('remark', '')),
        ];
    }
}
