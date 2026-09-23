<?php
/**
 * 节点端主程序（本地控制 API 入口）。
 *
 * 启动： php -S 0.0.0.0:8787 /opt/incus-node/server.php
 * 由 systemd 单元 incus-node-api.service 常驻。
 *
 * 结构：被控程序 + 本地 SQLite 库 + Incus。
 * 面板/财务系统作为客户端，用 {ip, 端口, key} 调用本接口远程控制本节点。
 */
declare(strict_types=1);

require __DIR__ . '/lib/Incus.php';
require __DIR__ . '/lib/Db.php';
require __DIR__ . '/lib/NodeStore.php';
require __DIR__ . '/lib/NodeApi.php';
require __DIR__ . '/lib/Version.php';

use Agent\Db;
use Agent\Incus;
use Agent\NodeApi;
use Agent\NodeStore;

$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['code' => 500, 'message' => '缺少 config.php']);
    return;
}
$config = require $configFile;

Db::connect($config['db_file'] ?? (__DIR__ . '/node.db'));

$incus = new Incus($config['incus_bin'] ?? 'incus', $config['storage_pool'] ?? 'default', $config['bridge'] ?? 'incusbr0');
$store = new NodeStore();
$api = new NodeApi($incus, $store, $config);

$headers = [];
foreach ($_SERVER as $k => $v) {
    if (str_starts_with((string)$k, 'HTTP_')) {
        $headers[strtolower(str_replace('_', '-', substr((string)$k, 5)))] = (string)$v;
    }
}

$path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$body = (string)file_get_contents('php://input');

$api->handle((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'), $path, $headers, $body);
