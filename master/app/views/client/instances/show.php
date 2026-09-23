<?php $i = $instance; $l = $live; ?>
<div class="grid cols-2">
  <div class="card">
    <div class="card-head"><h2>实例信息</h2><?= instance_status_badge($i['status']) ?></div>
    <div class="card-body">
      <div class="kv">
        <dt>名称</dt><dd><?= e($i['name']) ?></dd>
        <dt>状态</dt><dd><?= instance_status_badge($l['status'] ?? $i['status']) ?></dd>
        <dt>规格</dt><dd><?= (int)$i['cpu'] ?> 核 / <?= (int)$i['memory_mb'] ?> MB / <?= (int)$i['disk_gb'] ?> GB</dd>
        <dt>IP 地址</dt><dd class="mono"><?= e(($l['ip'] ?? $i['ip']) ?: '-') ?></dd>
        <dt>访问 Key</dt><dd class="mono"><?= e($i['access_key']) ?></dd>
        <dt>用量</dt><dd>CPU <?= (float)($l['cpu_usage'] ?? $i['cpu_usage']) ?>% · 内存 <?= (int)($l['mem_usage_mb'] ?? $i['mem_usage_mb']) ?> MB</dd>
        <dt>登录用户</dt><dd><?= e($l['os_user'] ?? 'root') ?></dd>
        <dt>登录密码</dt><dd class="mono"><?= e($l['root_password'] ?? '-') ?></dd>
      </div>
    </div>
  </div>
  <div class="card">
    <div class="card-head"><h2>操作</h2></div>
    <div class="card-body">
      <div class="flex mb-2">
        <form class="inline-form" method="post" action="/user/instances/<?= (int)$i['id'] ?>/action"><?= csrf_field() ?><input type="hidden" name="type" value="start"><button class="btn sm success" type="submit">开机</button></form>
        <form class="inline-form" method="post" action="/user/instances/<?= (int)$i['id'] ?>/action"><?= csrf_field() ?><input type="hidden" name="type" value="stop"><button class="btn sm ghost" type="submit">关机</button></form>
        <form class="inline-form" method="post" action="/user/instances/<?= (int)$i['id'] ?>/action"><?= csrf_field() ?><input type="hidden" name="type" value="reboot"><button class="btn sm ghost" type="submit">重启</button></form>
      </div>
      <form method="post" action="/user/instances/<?= (int)$i['id'] ?>/action" class="mb-2">
        <?= csrf_field() ?><input type="hidden" name="type" value="reset_password">
        <label class="muted" style="font-size:12px">重置密码</label>
        <div class="flex"><input type="text" name="password" placeholder="留空自动生成" style="flex:1"><button class="btn sm ghost" type="submit">重置</button></div>
      </form>
      <form method="post" action="/user/instances/<?= (int)$i['id'] ?>/action" class="mb-2">
        <?= csrf_field() ?><input type="hidden" name="type" value="snapshot_create"><button class="btn sm subtle" type="submit">创建快照</button>
      </form>
      <form method="post" action="/user/instances/<?= (int)$i['id'] ?>/delete" onsubmit="return confirm('确认删除？此操作不可恢复')">
        <?= csrf_field() ?><button class="btn sm danger" type="submit">删除服务器</button>
      </form>
    </div>
  </div>
</div>
<a class="btn ghost" href="/user/instances">返回列表</a>
