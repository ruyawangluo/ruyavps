<?php
namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Db;
use App\Core\Http;
use App\Core\Log;

class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (Auth::adminId() > 0) {
            $this->redirect('/admin');
        }
        $this->view('admin/login', ['title' => '管理员登录'], 'auth');
    }

    public function login(): void
    {
        Csrf::check();
        $username = trim((string)Http::input('username', ''));
        $password = (string)Http::input('password', '');

        $admin = Db::one('SELECT * FROM admins WHERE username = ?', [$username]);
        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            flash('error', '用户名或密码错误');
            $this->redirect('/admin/login');
        }
        if ((int)$admin['status'] !== 1) {
            flash('error', '账号已被禁用');
            $this->redirect('/admin/login');
        }

        Auth::loginAdmin((int)$admin['id']);
        Db::update('admins', [
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => Http::ip(),
        ], 'id = :id', ['id' => $admin['id']]);
        Log::write('admin', (int)$admin['id'], 'admin.login', $admin['username']);
        $this->redirect('/admin');
    }

    public function logout(): void
    {
        Auth::logoutAdmin();
        $this->redirect('/admin/login');
    }
}
