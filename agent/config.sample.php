<?php
/**
 * 节点端（被控程序）配置示例，复制为 config.php 后修改。
 *
 * 节点自治：本程序 + 本地 SQLite 库 + Incus。面板只需 {ip, 端口, key} 即可远程控制。
 */
return [
    // 节点名称
    'node_name' => 'node-1',

    // 对外 IP（面板回连用）。留空则自动探测第一个 IPv4。
    'node_ip'   => '',

    // 本地 API 监听
    'api_bind'  => '0.0.0.0',
    'api_port'  => 8787,

    // 访问密钥（面板端配置节点时填写；请求头 X-Node-Token）
    'node_key'  => 'change-this-node-key',

    // 节点本地数据库（SQLite）
    'db_file'   => __DIR__ . '/node.db',

    // Incus
    'incus_bin'    => 'incus',
    'storage_pool' => 'default',
    'bridge'       => 'incusbr0',

    // 实例名前缀（安全边界）
    'instance_prefix' => 'c-',

    // 日志
    'log_file'  => __DIR__ . '/agent.log',
];
