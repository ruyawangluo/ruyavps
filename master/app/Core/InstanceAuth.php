<?php
namespace App\Core;

/**
 * 单机 API 鉴权：用「每台机器专属访问 Key」调用，可选校验用户邮箱。
 * 供上层/前台系统按机器粒度控制某台机器。
 */
class InstanceAuth
{
    public static function authenticate(): array
    {
        $key = Http::header('X-Instance-Key');
        if ($key === '') {
            // 也允许放在请求体
            $key = (string)Http::input('access_key', '');
        }
        if ($key === '') {
            throw new \RuntimeException('缺少实例访问 Key', 401);
        }
        $inst = Db::one('SELECT * FROM instances WHERE access_key = ?', [$key]);
        if (!$inst) {
            throw new \RuntimeException('访问 Key 无效', 401);
        }

        $email = trim((string)(Http::header('X-Instance-Email') !== '' ? Http::header('X-Instance-Email') : Http::input('email', '')));
        if ($email !== '') {
            $owner = $inst['user_id'] ? Db::value('SELECT email FROM users WHERE id = ?', [$inst['user_id']]) : '';
            if (!hash_equals((string)$owner, $email)) {
                throw new \RuntimeException('该 Key 不属于此用户', 403);
            }
        }
        return $inst;
    }
}
