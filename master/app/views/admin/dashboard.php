<div class="grid cols-4">
  <div class="stat c1"><div class="label">容器</div><div class="value"><?= (int)$stats['containers'] ?> <small>个</small></div><div class="sub">LXC 实例</div></div>
  <div class="stat c2"><div class="label">虚拟机</div><div class="value"><?= (int)$stats['vms'] ?> <small>个</small></div><div class="sub">KVM 实例</div></div>
  <div class="stat c3"><div class="label">运行中</div><div class="value"><?= (int)$stats['running'] ?> <small>/ <?= (int)$stats['total'] ?></small></div><div class="sub">实例总数</div></div>
  <div class="stat c4"><div class="label">异常</div><div class="value"><?= (int)$stats['error'] ?></div><div class="sub">需关注</div></div>
</div>

<div class="grid cols-3 mt-3">
  <div class="stat c6"><div class="label">计算节点</div><div class="value"><?= (int)$nodeStats['online'] ?> <small>/ <?= (int)$nodeStats['total'] ?></small></div><div class="sub">在线 / 总数</div></div>
  <div class="stat c5"><div class="label">用户</div><div class="value"><?= (int)$userCount ?></div><div class="sub">注册用户</div></div>
  <div class="stat c2"><div class="label">架构</div><div class="value" style="font-size:18px">节点自治</div><div class="sub">面板仅作控制面</div></div>
</div>

<div class="between mt-3 mb-2">
  <h2 style="margin:0">节点</h2>
  <a class="btn sm ghost" href="/admin/nodes">管理节点</a>
</div>
<?php if (!$nodes): ?>
  <div class="card"><div class="card-body empty">还没有节点，请到 <a href="/admin/nodes">计算节点</a> 接入。</div></div>
<?php else: ?>
<div class="tiles">
  <?php foreach ($nodes as $n): ?>
    <div class="tile">
      <div class="tile-mark"><?= e(mb_substr($n['name'], 0, 1)) ?></div>
      <div class="tile-main">
        <div class="tile-title"><?= e($n['name']) ?> <?= node_status_badge((int)$n['status']) ?></div>
        <div class="tile-sub mono"><?= e($n['ip']) ?>:<?= (int)$n['port'] ?></div>
        <div class="tile-kv">
          <div>Incus <b class="mono"><?= e($n['incus_version'] ?: '-') ?></b></div>
          <div>节点程序 <b class="mono"><?= e($n['agent_version'] ?: '-') ?></b></div>
          <div>最后探测 <b><?= e($n['last_seen'] ?: '从未') ?></b></div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="grid cols-2 mt-3">
  <div class="card">
    <div class="card-head"><h2>最近实例</h2><a class="btn sm ghost" href="/admin/instances">全部</a></div>
    <div class="card-body" style="padding:0">
      <div class="table-wrap"><table>
        <thead><tr><th>名称</th><th>节点</th><th>状态</th><th>IP</th></tr></thead>
        <tbody>
        <?php foreach ($recentInsts as $i): ?>
          <tr><td><a href="/admin/instances/<?= (int)$i['id'] ?>"><?= e($i['name']) ?></a></td><td class="muted"><?= e($i['node_name'] ?? '-') ?></td><td><?= instance_status_badge($i['status']) ?></td><td class="mono"><?= e($i['ip'] ?: '-') ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$recentInsts): ?><tr><td colspan="4" class="empty">暂无实例</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>
  </div>
  <div class="card">
    <div class="card-head"><h2>最近操作</h2><a class="btn sm ghost" href="/admin/logs">全部</a></div>
    <div class="card-body" style="padding:0">
      <div class="table-wrap"><table>
        <thead><tr><th>操作</th><th>对象</th><th>时间</th></tr></thead>
        <tbody>
        <?php foreach ($recentLogs as $l): ?>
          <tr><td class="mono"><?= e($l['action']) ?></td><td><?= e($l['target']) ?></td><td class="muted"><?= e($l['created_at']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$recentLogs): ?><tr><td colspan="3" class="empty">暂无日志</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>
  </div>
</div>
