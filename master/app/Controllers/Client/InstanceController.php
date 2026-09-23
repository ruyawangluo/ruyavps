<?php
namespace App\Controllers\Client;

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
        $user = Auth::requireUser();
        $this->view('client/instances/index', [
            'title'     => '云服务器',
            'user'      => $user,
            'instances' => Db::all("SELECT * FROM instances WHERE user_id = ? AND status <> 'deleted' ORDER BY id DESC", [$user['id']]),
        ], 'client/layout');
    }

    public function create(): void
    {
        $user = Auth::requireUser();
        $this->view('client/instances/form', [
            'title' => '新建云服务器',
            'user'  => $user,
            'nodes' => Db::all('SELECT * FROM nodes WHERE enabled = 1 ORDER BY (status=1) DESC, id'),
        ], 'client/layout');
    }

    public function imagesJson(): void
    {
        Auth::requireUser();
        $nodeId = (int)Http::input('node_id', 0);
        $node = $nodeId ? NodeService::find($nodeId) : NodeService::pickSchedulable();
        if (!$node) {
            Http::jsonResponse(['code' => 0, 'data' => ['images' => []]]);
        }
        $res = NodeService::client($node)->images();
        Http::jsonResponse(['code' => 0, 'data' => ['images' => $res['data']['images'] ?? [], 'warning' => $res['data']['warning'] ?? '']]);
    }

    public function store(): void
    {
        $user = Auth::requireUser();
        Csrf::check();
        try {
            $row = InstanceService::create((int)$user['id'], [
                'name'          => trim((string)Http::input('name', '')),
                'node_id'       => (int)Http::input('node_id', 0),
                'source'        => trim((string)Http::input('source', '')),
                'variant'       => (string)Http::input('variant', 'container'),
                'cpu'           => (int)Http::input('cpu', 1),
                'memory_mb'     => (int)Http::input('memory_mb', 512),
                'disk_gb'       => (int)Http::input('disk_gb', 15),
                'root_password' => (string)Http::input('root_password', ''),
            ]);
            Log::write('user', (int)$user['id'], 'instance.create', '#' . ($row['id'] ?? ''));
            flash('success', '创建成功。');
            $this->redirect('/user/instances/' . (int)$row['id']);
        } catch (\Throwable $e) {
            flash('error', '创建失败：' . $e->getMessage());
            $this->redirect('/user/instances/create');
        }
    }

    public function show(string $id): void
    {
        $user = Auth::requireUser();
        $inst = Db::one('SELECT * FROM instances WHERE id = ? AND user_id = ?', [(int)$id, $user['id']]);
        if (!$inst) {
            $this->abort404('实例不存在');
        }
        $live = null;
        $node = NodeService::find((int)$inst['node_id']);
        if ($node) {
            $res = NodeService::client($node)->getInstance((string)$inst['incus_name']);
            $live = $res['ok'] ? $res['data'] : null;
        }
        $this->view('client/instances/show', [
            'title'    => '实例详情',
            'user'     => $user,
            'instance' => $inst,
            'live'     => $live,
        ], 'client/layout');
    }

    public function action(string $id): void
    {
        $user = Auth::requireUser();
        Csrf::check();
        $inst = Db::one('SELECT * FROM instances WHERE id = ? AND user_id = ?', [(int)$id, $user['id']]);
        if (!$inst) {
            $this->abort404('实例不存在');
        }
        $type = (string)Http::input('type', '');
        $allowed = ['start', 'stop', 'reboot', 'reset_password', 'exec', 'snapshot_create'];
        if (!in_array($type, $allowed, true)) {
            flash('error', '不支持的操作');
            $this->redirect('/user/instances/' . $id);
        }
        try {
            $res = InstanceService::action((int)$id, $type, ['password' => (string)Http::input('password', '')]);
            flash($res['ok'] ? 'success' : 'error', $res['ok'] ? '操作成功' : $res['error']);
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        $this->redirect('/user/instances/' . $id);
    }

    public function destroy(string $id): void
    {
        $user = Auth::requireUser();
        Csrf::check();
        $inst = Db::one('SELECT * FROM instances WHERE id = ? AND user_id = ?', [(int)$id, $user['id']]);
        if ($inst) {
            try {
                InstanceService::action((int)$id, 'delete');
                flash('success', '已删除');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
        }
        $this->redirect('/user/instances');
    }
}
