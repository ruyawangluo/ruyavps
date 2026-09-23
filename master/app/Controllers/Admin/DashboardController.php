<?php
namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Db;
use App\Services\InstanceService;
use App\Services\NodeService;

class DashboardController extends Controller
{
    public function home(): void
    {
        if (Auth::adminId() > 0) {
            $this->redirect('/admin');
        }
        if (Auth::userId() > 0) {
            $this->redirect('/user');
        }
        $this->redirect('/user/login');
    }

    public function index(): void
    {
        $admin = Auth::requireAdmin();
        NodeService::refreshAll();
        InstanceService::refreshAll();

        $this->view('admin/dashboard', [
            'title'       => '控制台',
            'admin'       => $admin,
            'stats'       => InstanceService::stats(),
            'nodeStats'   => NodeService::stats(),
            'userCount'   => (int)Db::value('SELECT COUNT(*) FROM users'),
            'nodes'       => NodeService::all(),
            'recentInsts' => InstanceService::all(8),
            'recentLogs'  => Db::all('SELECT * FROM logs ORDER BY id DESC LIMIT 10'),
        ], 'admin/layout');
    }
}
