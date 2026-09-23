<?php
namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Db;
use App\Core\Http;

class LogController extends Controller
{
    public function index(): void
    {
        $admin = Auth::requireAdmin();
        $page = max(1, (int)Http::input('page', 1));
        $size = 50;
        $offset = ($page - 1) * $size;
        $total = (int)Db::value('SELECT COUNT(*) FROM logs');
        $this->view('admin/logs/index', [
            'title' => '操作日志',
            'admin' => $admin,
            'logs'  => Db::all("SELECT * FROM logs ORDER BY id DESC LIMIT $size OFFSET $offset"),
            'page'  => $page,
            'total' => $total,
            'size'  => $size,
        ], 'admin/layout');
    }
}
