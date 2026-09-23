<div class="between mb-2">
  <h2 style="margin:0">我的云服务器</h2>
  <a class="btn" href="/user/instances/create">新建云服务器</a>
</div>
<?php if (!$instances): ?>
  <div class="card"><div class="card-body empty">还没有云服务器</div></div>
<?php else: ?>
<div class="tiles">
  <?php foreach ($instances as $i): ?>
    <div class="tile">
      <div class="tile-mark"><?= (int)$i['cpu'] ?>C</div>
      <div class="tile-main">
        <div class="tile-title"><a href="/user/instances/<?= (int)$i['id'] ?>"><?= e($i['name']) ?></a> <?= instance_status_badge($i['status']) ?></div>
        <div class="tile-sub mono"><?= e($i['incus_name']) ?></div>
        <div class="tile-kv">
          <div>规格 <b><?= (int)$i['cpu'] ?>C / <?= (int)$i['memory_mb'] ?>M / <?= (int)$i['disk_gb'] ?>G</b></div>
          <div>IP <b class="mono"><?= e($i['ip'] ?: '-') ?></b></div>
        </div>
      </div>
      <div class="tile-actions"><a class="btn sm ghost" href="/user/instances/<?= (int)$i['id'] ?>">管理</a></div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
