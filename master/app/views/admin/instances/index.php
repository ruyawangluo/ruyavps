<div class="between mb-2">
  <h2 style="margin:0">云服务器</h2>
  <a class="btn" href="/admin/instances/create">开通云服务器</a>
</div>

<?php if (!$instances): ?>
  <div class="card"><div class="card-body empty">还没有云服务器，点击右上角「开通云服务器」。</div></div>
<?php else: ?>
<div class="tiles">
  <?php foreach ($instances as $i): ?>
    <div class="tile">
      <div class="tile-mark"><?= $i['variant'] === 'vm' ? 'VM' : 'LXC' ?></div>
      <div class="tile-main">
        <div class="tile-title">
          <a href="/admin/instances/<?= (int)$i['id'] ?>"><?= e($i['name']) ?></a>
          <?= instance_status_badge($i['status']) ?>
        </div>
        <div class="tile-sub mono"><?= e($i['incus_name']) ?></div>
        <div class="tile-kv">
          <div>用户 <b><?= e($i['user_email'] ?: '管理员') ?></b></div>
          <div>节点 <b><?= e($i['node_name'] ?? '-') ?></b></div>
          <div>规格 <b><?= (int)$i['cpu'] ?>C / <?= (int)$i['memory_mb'] ?>M / <?= (int)$i['disk_gb'] ?>G</b></div>
          <div>IP <b class="mono"><?= e($i['ip'] ?: '-') ?></b></div>
          <div>用量 <b><?= (float)$i['cpu_usage'] ?>% · <?= (int)$i['mem_usage_mb'] ?>MB</b></div>
          <div>访问 Key <b class="mono"><?= e($i['access_key']) ?></b></div>
        </div>
      </div>
      <div class="tile-actions">
        <a class="btn sm ghost" href="/admin/instances/<?= (int)$i['id'] ?>">详情</a>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
