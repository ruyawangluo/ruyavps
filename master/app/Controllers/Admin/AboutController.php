<?php
namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Db;
use App\Core\Version;
use App\Services\NodeService;

/**
 * 关于（面板/节点版本信息）。更新通过服务器上的安装脚本进行。
 */
class AboutController extends Controller
{
    public function index(): void
    {
        $admin = Auth::requireAdmin();
        $mysqlVersion = '';
        try {
            $mysqlVersion = (string)Db::value('SELECT VERSION()');
        } catch (\Throwable $e) {
        }
        $this->view('admin/about', [
            'title'         => '关于',
            'admin'         => $admin,
            'masterVersion' => Version::MASTER,
            'phpVersion'    => PHP_VERSION,
            'mysqlVersion'  => $mysqlVersion,
            'dbName'        => (string)\App\Core\Config::get('db.name'),
            'installedAt'   => $this->installedAt(),
            'serverTime'    => date('Y-m-d H:i:s'),
            'nodes'         => NodeService::all(),
            'appRoot'       => APP_ROOT,
        ], 'admin/layout');
    }

    private function installedAt(): string
    {
        $cfg = APP_ROOT . '/config.php';
        $t = is_file($cfg) ? filemtime($cfg) : false;
        return $t ? date('Y-m-d H:i:s', $t) : '未知';
    }
}
