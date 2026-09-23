<?php
namespace App\Controllers\Client;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Db;

class DashboardController extends Controller
{
    public function index(): void
    {
        $user = Auth::requireUser();
        $instances = Db::all("SELECT * FROM instances WHERE user_id = ? AND status <> 'deleted' ORDER BY id DESC", [$user['id']]);
        $this->view('client/dashboard', [
            'title'     => '我的云服务器',
            'user'      => $user,
            'instances' => $instances,
        ], 'client/layout');
    }
}
