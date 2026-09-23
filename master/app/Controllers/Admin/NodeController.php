<?php
namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Db;
use App\Core\Http;
use App\Core\Log;
use App\Services\NodeService;

class NodeController extends Controller
{
    public function index(): void
    {
        $admin = Auth::requireAdmin();
        foreach (NodeService::all() as $n) {
            NodeService::refresh($n);
        }
        $this->view('admin/nodes/index', [
            'title' => '计算节点',
            'admin' => $admin,
            'nodes' => NodeService::all(),
        ], 'admin/layout');
    }

    public function create(): void
    {
        $admin = Auth::requireAdmin();
        $this->view('admin/nodes/form', ['title' => '接入节点', 'admin' => $admin, 'node' => null], 'admin/layout');
    }

    public function store(): void
    {
        $admin = Auth::requireAdmin();
        Csrf::check();
        $d = $this->collect();
        if ($d['ip'] === '' || $d['node_key'] === '') {
            flash('error', 'IP 与节点密钥不能为空');
            $this->redirect('/admin/nodes/create');
        }
        $id = NodeService::create($d);
        $node = NodeService::find($id);
        $res = NodeService::refresh($node);
        Log::write('admin', (int)$admin['id'], 'node.create', $d['name']);
        flash($res['ok'] ? 'success' : 'error', $res['ok'] ? '节点已接入并在线。' : '节点已保存，但连接失败：' . ($res['error'] ?? ''));
        $this->redirect('/admin/nodes');
    }

    public function edit(string $id): void
    {
        $admin = Auth::requireAdmin();
        $node = NodeService::find((int)$id);
        if (!$node) {
            $this->abort404('节点不存在');
        }
        $this->view('admin/nodes/form', ['title' => '编辑节点', 'admin' => $admin, 'node' => $node], 'admin/layout');
    }

    public function update(string $id): void
    {
        $admin = Auth::requireAdmin();
        Csrf::check();
        $node = NodeService::find((int)$id);
        if (!$node) {
            $this->abort404('节点不存在');
        }
        $d = $this->collect();
        Db::update('nodes', [
            'name'    => $d['name'],
            'ip'      => $d['ip'],
            'port'    => $d['port'],
            'node_key'=> $d['node_key'],
            'region'  => $d['region'],
            'enabled' => $d['enabled'],
            'remark'  => $d['remark'],
        ], 'id = :id', ['id' => $node['id']]);
        Log::write('admin', (int)$admin['id'], 'node.update', $d['name']);
        flash('success', '节点已更新');
        $this->redirect('/admin/nodes');
    }

    public function test(string $id): void
    {
        Auth::requireAdmin();
        Csrf::check();
        $node = NodeService::find((int)$id);
        if ($node) {
            $res = NodeService::refresh($node);
            flash($res['ok'] ? 'success' : 'error', $res['ok'] ? '节点在线：' . json_encode($res['data'], JSON_UNESCAPED_UNICODE) : '连接失败：' . ($res['error'] ?? ''));
        }
        $this->redirect('/admin/nodes');
    }

    public function toggle(string $id): void
    {
        Auth::requireAdmin();
        Csrf::check();
        $node = NodeService::find((int)$id);
        if ($node) {
            Db::update('nodes', ['enabled' => (int)$node['enabled'] === 1 ? 0 : 1], 'id = :id', ['id' => $node['id']]);
        }
        $this->redirect('/admin/nodes');
    }

    public function destroy(string $id): void
    {
        $admin = Auth::requireAdmin();
        Csrf::check();
        $node = NodeService::find((int)$id);
        if ($node) {
            $count = (int)Db::value("SELECT COUNT(*) FROM instances WHERE node_id = ? AND status <> 'deleted'", [$node['id']]);
            if ($count > 0) {
                flash('error', '该节点下仍有实例，无法移除');
                $this->redirect('/admin/nodes');
            }
            Db::delete('nodes', 'id = ?', [$node['id']]);
            Log::write('admin', (int)$admin['id'], 'node.delete', $node['name']);
            flash('success', '节点已移除');
        }
        $this->redirect('/admin/nodes');
    }

    private function collect(): array
    {
        return [
            'name'     => trim((string)Http::input('name', '')),
            'ip'       => trim((string)Http::input('ip', '')),
            'port'     => (int)Http::input('port', 8787) ?: 8787,
            'node_key' => trim((string)Http::input('node_key', '')),
            'region'   => trim((string)Http::input('region', '默认区域')) ?: '默认区域',
            'enabled'  => (int)Http::input('enabled', 1),
            'remark'   => trim((string)Http::input('remark', '')),
        ];
    }
}
