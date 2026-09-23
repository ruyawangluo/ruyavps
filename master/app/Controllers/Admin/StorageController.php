<?php
namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Http;
use App\Services\NodeService;

/**
 * 资源管理 · 存储池（数据来自节点，只读查看）。
 */
class StorageController extends Controller
{
    public function index(): void
    {
        $admin = Auth::requireAdmin();
        $nodes = NodeService::all();
        $nodeId = (int)Http::input('node_id', 0);
        if ($nodeId <= 0 && $nodes) {
            $nodeId = (int)$nodes[0]['id'];
        }
        $pools = [];
        $node = $nodeId ? NodeService::find($nodeId) : null;
        if ($node) {
            $res = NodeService::client($node)->storage();
            $pools = $res['data']['storage_pools'] ?? [];
        }
        $this->view('admin/storage/index', [
            'title'  => '存储池',
            'admin'  => $admin,
            'nodes'  => $nodes,
            'nodeId' => $nodeId,
            'pools'  => $pools,
        ], 'admin/layout');
    }
}
