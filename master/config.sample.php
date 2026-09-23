<?php
/**
 * 主控端配置示例
 * 复制为 config.php 并修改。
 */
return [
    // 调试模式
    'debug'      => false,

    // 站点地址（被控端注册回调、生成绝对链接用）
    'site_url'   => 'http://your-master.example.com',

    // 数据库
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'incus_panel',
        'user'    => 'incus',
        'pass'    => 'change-me',
        'charset' => 'utf8mb4',
    ],

    // 数据加密密钥（用于实例 root 密码等敏感字段，AES-256-GCM）
    // 请务必修改，修改后旧数据将无法解密
    'app_key'    => 'please-change-this-32-char-secret-key',

    // 会话名
    'session_name' => 'incus_panel_sid',

    // 被控端任务轮询：单次最多拉取任务数
    'task_batch' => 5,

    // 被控心跳超时（秒），超过视为离线
    'heartbeat_timeout' => 90,

    // 默认分页大小
    'page_size' => 20,
];
