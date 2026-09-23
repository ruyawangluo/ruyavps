<?php
/**
 * PHP 内置服务器路由脚本：
 *   php -S 0.0.0.0:8080 -t master/public master/public/router.php
 * 已存在的静态文件直接返回，其余交给 index.php。
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) {
    return false;
}
require __DIR__ . '/index.php';
