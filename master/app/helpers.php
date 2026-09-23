<?php
/**
 * 全局辅助函数。
 */
declare(strict_types=1);

if (!function_exists('e')) {
    function e($v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('old')) {
    function old(string $key, $default = '')
    {
        return $_POST[$key] ?? $default;
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return App\Core\Csrf::field();
    }
}

if (!function_exists('config')) {
    function config(string $key, $default = null)
    {
        return App\Core\Config::get($key, $default);
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $base = rtrim((string)config('site_url', ''), '/');
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('flash')) {
    function flash(?string $type = null, ?string $message = null): ?array
    {
        if ($type !== null) {
            $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
            return null;
        }
        $f = $_SESSION['_flash'] ?? null;
        unset($_SESSION['_flash']);
        return $f;
    }
}

if (!function_exists('admin_url')) {
    function admin_url(string $path = ''): string
    {
        return '/admin' . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('instance_status_badge')) {
    function instance_status_badge(string $status): string
    {
        $map = [
            'running'    => ['运行中', 'green'],
            'stopped'    => ['已停止', 'gray'],
            'creating'   => ['创建中', 'blue'],
            'starting'   => ['启动中', 'blue'],
            'stopping'   => ['停止中', 'blue'],
            'restarting' => ['重启中', 'blue'],
            'rebuilding' => ['重装中', 'orange'],
            'deleting'   => ['删除中', 'orange'],
            'deleted'    => ['已删除', 'gray'],
            'error'      => ['异常', 'red'],
        ];
        [$text, $color] = $map[$status] ?? [$status, 'gray'];
        return '<span class="badge ' . $color . '">' . e($text) . '</span>';
    }
}

if (!function_exists('task_status_badge')) {
    function task_status_badge(int $status): string
    {
        $map = [
            0 => ['待执行', 'gray'],
            1 => ['执行中', 'blue'],
            2 => ['成功', 'green'],
            3 => ['失败', 'red'],
        ];
        [$text, $color] = $map[$status] ?? ['未知', 'gray'];
        return '<span class="badge ' . $color . '">' . e($text) . '</span>';
    }
}

if (!function_exists('node_status_badge')) {
    function node_status_badge(int $status): string
    {
        return $status === 1
            ? '<span class="badge green">在线</span>'
            : '<span class="badge red">离线</span>';
    }
}

if (!function_exists('user_url')) {
    function user_url(string $path = ''): string
    {
        return '/user' . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('github_raw')) {
    /** 拼出仓库内某文件的 GitHub Raw 地址（在节点上 curl 用）。 */
    function github_raw(string $path = ''): string
    {
        $base = rtrim((string)config('github_raw', 'https://raw.githubusercontent.com/ruyawangluo/ruyavps/main'), '/');
        return $base . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('one_liner')) {
    /** 生成「下载到临时文件再执行」的一行命令（交互式脚本适用）。 */
    function one_liner(string $url): string
    {
        $name = basename(parse_url($url, PHP_URL_PATH) ?: 'script.sh');
        return sprintf('curl -fsSL %s -o /tmp/%s && sudo bash /tmp/%s', $url, $name, $name);
    }
}
