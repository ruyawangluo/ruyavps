<?php /** @var string $masterVersion,$phpVersion,$mysqlVersion,$dbName,$installedAt,$serverTime,$appRoot @var array $nodes */ ?>
<div class="grid cols-2">
  <div class="card">
    <div class="card-head"><h2>版本信息</h2></div>
    <div class="card-body">
      <div class="kv">
        <dt>面板版本</dt><dd><span class="badge">v<?= e($masterVersion) ?></span></dd>
        <dt>PHP 版本</dt><dd><?= e($phpVersion) ?></dd>
        <dt>MySQL 版本</dt><dd><?= e($mysqlVersion ?: '未知') ?></dd>
        <dt>数据库名</dt><dd class="mono"><?= e($dbName) ?></dd>
        <dt>安装时间</dt><dd><?= e($installedAt) ?></dd>
        <dt>服务器时间</dt><dd><?= e($serverTime) ?></dd>
        <dt>程序目录</dt><dd class="mono"><?= e($appRoot) ?></dd>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h2>升级方式</h2></div>
    <div class="card-body">
      <p class="muted" style="margin-top:0">升级面板请在服务器上运行安装脚本的 update 模式（自动覆盖程序并保留 config.php）：</p>
      <pre class="mono" style="background:#f6f8fc;border:1px solid var(--line);border-radius:10px;padding:12px;overflow:auto;font-size:12px">bash install/install-master.sh update</pre>
      <div class="form-hint">安装脚本：<code>install/install-master.sh</code></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-head"><h2>节点版本</h2></div>
  <div class="card-body" style="padding:0">
    <div class="table-wrap"><table>
      <thead><tr><th>节点</th><th>状态</th><th>接入地址</th><th>节点程序版本</th><th>Incus 版本</th></tr></thead>
      <tbody>
      <?php foreach ($nodes as $n): ?>
        <tr><td class="strong"><?= e($n['name']) ?></td><td><?= node_status_badge((int)$n['status']) ?></td><td class="mono"><?= e($n['ip']) ?>:<?= (int)$n['port'] ?></td><td class="mono"><?= e($n['agent_version'] ?: '-') ?></td><td class="mono"><?= e($n['incus_version'] ?: '-') ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$nodes): ?><tr><td colspan="5" class="empty">暂无节点</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div>
</div>

<div class="card">
  <div class="card-head"><h2>架构说明</h2></div>
  <div class="card-body">
    <ul style="margin:0;padding-left:20px" class="muted">
      <li>面板是纯 Web UI，只保存自己的数据库（管理员、用户、节点接入信息、实例索引）。</li>
      <li>节点端 = 被控程序 + 本地库（SQLite）+ Incus；实例、镜像、存储的真实数据都在节点上。</li>
      <li>节点只把 <code>ip / 端口 / key</code> 交给面板；面板通过节点 API 远程控制。</li>
      <li>节点程序版本在节点 <code>lib/Version.php</code>，升级节点端请重新执行安装命令。</li>
    </ul>
  </div>
</div>
