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

    /** SSH 安装节点。 */
    public function sshForm(): void
    {
        $admin = Auth::requireAdmin();
        $this->view('admin/nodes/ssh', [
            'title'    => 'SSH 安装节点',
            'admin'    => $admin,
            'sshReady' => extension_loaded('ssh2'),
            'result'   => $_SESSION['_ssh_result'] ?? null,
        ], 'admin/layout');
        unset($_SESSION['_ssh_result']);
    }

    public function sshInstall(): void
    {
        $admin = Auth::requireAdmin();
        Csrf::check();
        $host = trim((string)Http::input('host', ''));
        $port = (int)Http::input('port', 22) ?: 22;
        $user = trim((string)Http::input('user', 'root')) ?: 'root';
        $pass = (string)Http::input('password', '');
        $key  = (string)Http::input('private_key', '');
        if ($host === '') {
            flash('error', '请填写目标主机地址');
            $this->redirect('/admin/nodes/ssh');
        }
        if (!extension_loaded('ssh2')) {
            flash('error', 'PHP 未启用 ssh2 扩展，无法自动安装。请改为在节点上手动执行安装命令。');
            $this->redirect('/admin/nodes/ssh');
        }

        $cmd = sprintf('curl -fsSL %s/deploy/agent.sh | bash', rtrim((string)\App\Core\Config::get('site_url', ''), '/'));
        $res = $this->runSsh($host, $port, $user, $pass, $key, $cmd);

        // 尝试从输出解析节点接入信息并自动接入
        $nodeId = 0;
        $out = (string)($res['output'] ?? '');
        $ip = $portNode = $nodeKey = '';
        if (preg_match('/IP\s*:\s*([0-9.]+)/', $out, $m)) { $ip = $m[1]; }
        if (preg_match('/端口\s*:\s*(\d+)/', $out, $m)) { $portNode = (int)$m[1]; }
        if (preg_match('/KEY\s*:\s*([0-9a-fA-F]+)/', $out, $m)) { $nodeKey = $m[1]; }
        if ($ip !== '' && $nodeKey !== '') {
            $nodeId = NodeService::create([
                'name' => $host, 'ip' => $ip, 'port' => $portNode ?: 8787, 'node_key' => $nodeKey, 'enabled' => 1,
            ]);
            NodeService::refresh(NodeService::find($nodeId));
        }

        Log::write('admin', (int)$admin['id'], 'node.ssh_install', $host, ['ok' => $res['ok']]);
        $_SESSION['_ssh_result'] = [
            'ok' => $res['ok'], 'message' => $res['message'], 'command' => $cmd,
            'output' => $out, 'node_id' => $nodeId,
        ];
        $this->redirect('/admin/nodes/ssh');
    }

    private function runSsh(string $host, int $port, string $user, string $password, string $privateKey, string $command): array
    {
        $conn = @ssh2_connect($host, $port);
        if (!$conn) {
            return ['ok' => false, 'message' => 'SSH 连接失败（' . $host . ':' . $port . '）', 'output' => ''];
        }
        $auth = false;
        if (trim($privateKey) !== '') {
            $tmp = tempnam(sys_get_temp_dir(), 'key_');
            file_put_contents($tmp, $privateKey);
            chmod($tmp, 0600);
            $auth = @ssh2_auth_pubkey_file($conn, $user, $tmp . '.pub', $tmp);
            @unlink($tmp);
        }
        if (!$auth && $password !== '') {
            $auth = @ssh2_auth_password($conn, $user, $password);
        }
        if (!$auth) {
            return ['ok' => false, 'message' => 'SSH 认证失败', 'output' => ''];
        }
        $stream = @ssh2_exec($conn, $command);
        if (!$stream) {
            return ['ok' => false, 'message' => '命令下发失败', 'output' => ''];
        }
        stream_set_blocking($stream, true);
        $out = (string)stream_get_contents($stream);
        $errStream = @ssh2_fetch_stream($stream, SSH2_STREAM_STDERR);
        $err = $errStream ? (string)stream_get_contents($errStream) : '';
        return ['ok' => str_contains($out, '安装完成'), 'message' => '命令已执行', 'output' => trim($out . "\n" . $err)];
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
