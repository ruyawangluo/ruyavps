<?php
namespace App\Core;

/**
 * 登录态管理：区分管理员 / 用户。
 */
class Auth
{
    public static function loginAdmin(int $id): void
    {
        $_SESSION['admin_id'] = $id;
        session_regenerate_id(true);
    }

    public static function loginUser(int $id): void
    {
        $_SESSION['user_id'] = $id;
        session_regenerate_id(true);
    }

    public static function adminId(): int
    {
        return (int)($_SESSION['admin_id'] ?? 0);
    }

    public static function userId(): int
    {
        return (int)($_SESSION['user_id'] ?? 0);
    }

    public static function logoutAdmin(): void
    {
        unset($_SESSION['admin_id']);
    }

    public static function logoutUser(): void
    {
        unset($_SESSION['user_id']);
    }

    /** 要求管理员已登录，否则跳转登录页。 */
    public static function requireAdmin(): array
    {
        if (self::adminId() <= 0) {
            Http::redirect('/admin/login');
        }
        $admin = Db::one('SELECT * FROM admins WHERE id = ? AND status = 1', [self::adminId()]);
        if (!$admin) {
            self::logoutAdmin();
            Http::redirect('/admin/login');
        }
        return $admin;
    }

    /** 要求用户已登录，否则跳转登录页。 */
    public static function requireUser(): array
    {
        if (self::userId() <= 0) {
            Http::redirect('/user/login');
        }
        $user = Db::one('SELECT * FROM users WHERE id = ? AND status = 1', [self::userId()]);
        if (!$user) {
            self::logoutUser();
            Http::redirect('/user/login');
        }
        return $user;
    }
}
