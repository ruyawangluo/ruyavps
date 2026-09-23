<?php
/**
 * 面板端定时任务（可选）：刷新节点与实例状态缓存。
 * 建议 crontab 每 1-2 分钟执行：
 *   * * * * * php /path/to/master/bin/cron.php >> /var/log/incus-panel-cron.log 2>&1
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\InstanceService;
use App\Services\NodeService;

NodeService::refreshAll();
InstanceService::refreshAll();

echo '[' . date('Y-m-d H:i:s') . "] cron ok\n";
