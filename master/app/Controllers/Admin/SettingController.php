<?php
namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Db;
use App\Core\Http;
use App\Core\Log;
use App\Core\Setting;

class SettingController extends Controller
{
    public function index(): void
    {
        $admin = Auth::requireAdmin();
        $this->view('admin/settings/index', [
            'title'    => '系统设置',
            'admin'    => $admin,
            'settings' => (function () {
                $s = [];
                foreach (Db::all('SELECT * FROM settings') as $r) {
                    $s[$r['k']] = $r['v'];
                }
                return $s;
            })(),
            'nodes'    => Db::all('SELECT id, name FROM nodes ORDER BY id'),
        ], 'admin/layout');
    }

    public function update(): void
    {
        $admin = Auth::requireAdmin();
        Csrf::check();
        foreach (['site_name', 'site_url', 'task_poll_timeout', 'default_node_id'] as $k) {
            $v = Http::input($k, null);
            if ($v !== null) {
                Setting::set($k, (string)$v);
            }
        }
        Log::write('admin', (int)$admin['id'], 'setting.update');
        flash('success', '设置已保存');
        $this->redirect('/admin/settings');
    }

    public function regenerateOpenApi(): void
    {
        $admin = Auth::requireAdmin();
        Csrf::check();
        Setting::set('openapi_key', 'ok_' . bin2hex(random_bytes(12)));
        Setting::set('openapi_secret', bin2hex(random_bytes(24)));
        Log::write('admin', (int)$admin['id'], 'setting.openapi');
        flash('success', '已生成新的开放 API 凭据，请同步更新上游系统。');
        $this->redirect('/admin/settings');
    }
}
