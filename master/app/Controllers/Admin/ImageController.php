<?php
namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Db;
use App\Core\Http;
use App\Services\NodeService;

/**
 * 资源管理 · 镜像（数据来自节点，只读查看）。
 */
class ImageController extends Controller
{
    public function index(): void
    {
        $admin = Auth::requireAdmin();
        $nodes = NodeService::all();
        $nodeId = (int)Http::input('node_id', 0);
        if ($nodeId <= 0 && $nodes) {
            $nodeId = (int)$nodes[0]['id'];
        }
        $images = [];
        $warning = '';
        $node = $nodeId ? NodeService::find($nodeId) : null;
        if ($node) {
            $res = NodeService::client($node)->images();
            $images = $res['data']['images'] ?? [];
            $warning = $res['data']['warning'] ?? ($res['ok'] ? '' : $res['error']);
        }
        $this->view('admin/images/index', [
            'title'   => '镜像',
            'admin'   => $admin,
            'nodes'   => $nodes,
            'nodeId'  => $nodeId,
            'images'  => $images,
            'warning' => $warning,
        ], 'admin/layout');
    }
}
