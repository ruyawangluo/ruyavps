<?php
namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Db;
use App\Core\Http;
use App\Core\Log;
use App\Core\Random;

class UserController extends Controller
{
    public function index(): void
    {
        $admin = Auth::requireAdmin();
        $this->view('admin/users/index', [
            'title' => '客户管理',
            'admin' => $admin,
            'users' => Db::all('SELECT * FROM users ORDER BY id DESC'),
        ], 'admin/layout');
    }

    public function create(): void
    {
        $admin = Auth::requireAdmin();
        $this->view('admin/users/form', ['title' => '添加客户', 'admin' => $admin], 'admin/layout');
    }

    public function store(): void
    {
        $admin = Auth::requireAdmin();
        Csrf::check();
        $email = trim((string)Http::input('email', ''));
        $password = (string)Http::input('password', '');
        if ($email === '' || strlen($password) < 6) {
            flash('error', '邮箱不能为空，密码至少 6 位');
            $this->redirect('/admin/users/create');
        }
        if (Db::value('SELECT id FROM users WHERE email = ?', [$email])) {
            flash('error', '该邮箱已被注册');
            $this->redirect('/admin/users/create');
        }
        Db::insert('users', [
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'nickname'      => trim((string)Http::input('nickname', '')),
            'remark'        => trim((string)Http::input('remark', '')),
            'status'        => 1,
            'api_key'       => 'uk_' . bin2hex(random_bytes(12)),
            'api_secret'    => bin2hex(random_bytes(24)),
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
        Log::write('admin', (int)$admin['id'], 'user.create', $email);
        flash('success', '客户已创建');
        $this->redirect('/admin/users');
    }

    public function toggle(string $id): void
    {
        $admin = Auth::requireAdmin();
        Csrf::check();
        $user = Db::one('SELECT * FROM users WHERE id = ?', [(int)$id]);
        if ($user) {
            Db::update('users', ['status' => (int)$user['status'] === 1 ? 0 : 1], 'id = :id', ['id' => $user['id']]);
            Log::write('admin', (int)$admin['id'], 'user.toggle', $user['email']);
        }
        $this->redirect('/admin/users');
    }

    public function destroy(string $id): void
    {
        $admin = Auth::requireAdmin();
        Csrf::check();
        $user = Db::one('SELECT * FROM users WHERE id = ?', [(int)$id]);
        if ($user) {
            $count = (int)Db::value("SELECT COUNT(*) FROM instances WHERE user_id = ? AND status <> 'deleted'", [$user['id']]);
            if ($count > 0) {
                flash('error', '该客户名下仍有实例，无法删除');
                $this->redirect('/admin/users');
            }
            Db::delete('users', 'id = ?', [$user['id']]);
            Log::write('admin', (int)$admin['id'], 'user.delete', $user['email']);
            flash('success', '客户已删除');
        }
        $this->redirect('/admin/users');
    }
}
