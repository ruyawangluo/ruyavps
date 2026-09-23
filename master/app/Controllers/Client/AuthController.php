<?php
namespace App\Controllers\Client;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Db;
use App\Core\Http;
use App\Core\Log;
use App\Core\Random;

class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (Auth::userId() > 0) {
            $this->redirect('/user');
        }
        $this->view('client/login', ['title' => '用户登录'], 'auth');
    }

    public function login(): void
    {
        Csrf::check();
        $email = trim((string)Http::input('email', ''));
        $password = (string)Http::input('password', '');
        $user = Db::one('SELECT * FROM users WHERE email = ?', [$email]);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            flash('error', '邮箱或密码错误');
            $this->redirect('/user/login');
        }
        if ((int)$user['status'] !== 1) {
            flash('error', '账号已被禁用');
            $this->redirect('/user/login');
        }
        Auth::loginUser((int)$user['id']);
        Db::update('users', [
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => Http::ip(),
        ], 'id = :id', ['id' => $user['id']]);
        Log::write('user', (int)$user['id'], 'user.login', $user['email']);
        $this->redirect('/user');
    }

    public function showRegister(): void
    {
        if (Auth::userId() > 0) {
            $this->redirect('/user');
        }
        $this->view('client/register', ['title' => '注册账号'], 'auth');
    }

    public function register(): void
    {
        Csrf::check();
        $email = trim((string)Http::input('email', ''));
        $password = (string)Http::input('password', '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', '请输入正确的邮箱');
            $this->redirect('/user/register');
        }
        if (strlen($password) < 6) {
            flash('error', '密码至少 6 位');
            $this->redirect('/user/register');
        }
        if (Db::value('SELECT id FROM users WHERE email = ?', [$email])) {
            flash('error', '该邮箱已被注册');
            $this->redirect('/user/register');
        }
        $id = Db::insert('users', [
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'nickname'      => trim((string)Http::input('nickname', '')),
            'status'        => 1,
            'api_key'       => Random::key(16),
            'api_secret'    => Random::key(24),
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
        Auth::loginUser($id);
        Log::write('user', $id, 'user.register', $email);
        flash('success', '注册成功');
        $this->redirect('/user');
    }

    public function logout(): void
    {
        Auth::logoutUser();
        $this->redirect('/user/login');
    }
}
