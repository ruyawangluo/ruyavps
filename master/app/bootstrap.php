<?php
/**
 * 主控端引导文件：加载配置、注册自动加载、初始化会话。
 *
 * 注意：当 config.php 不存在时（未安装），不会中断，而是标记 CONFIG_EXISTS=false，
 * 由入口路由把请求导向 Web 安装向导 /install。
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_PATH', APP_ROOT . '/app');
define('VIEW_PATH', APP_PATH . '/views');

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = APP_PATH . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require APP_PATH . '/helpers.php';

$configFile = APP_ROOT . '/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("面板尚未安装。请在服务器上运行安装脚本： bash install/install-master.sh\n");
}
$GLOBALS['__config'] = require $configFile;

$debug = !empty($GLOBALS['__config']['debug']);
if ($debug) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED);
    ini_set('display_errors', '0');
}

date_default_timezone_set($GLOBALS['__config']['timezone'] ?? 'Asia/Shanghai');

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_name($GLOBALS['__config']['session_name'] ?? 'incus_panel_sid');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
