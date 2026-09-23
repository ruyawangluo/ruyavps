<?php $i = $instance; $l = $live; ?>
<div class="grid cols-2">
  <div class="card">
    <div class="card-head"><h2>实例信息</h2><?= instance_status_badge($i['status']) ?></div>
    <div class="card-body">
      <div class="kv">
        <dt>ID</dt><dd class="num">#<?= (int)$i['id'] ?></dd>
        <dt>名称</dt><dd><?= e($i['name']) ?></dd>
        <dt>Incus 名称</dt><dd class="mono"><?= e($i['incus_name']) ?></dd>
        <dt>访问 Key</dt><dd class="mono"><?= e($i['access_key']) ?></dd>
        <dt>类型</dt><dd><?= $i['variant'] === 'vm' ? '虚拟机' : '容器' ?></dd>
        <dt>归属用户</dt><dd><?= e($i['user_email'] ?: '管理员') ?></dd>
        <dt>节点</dt><dd><?= e($i['node_name'] ?? '-') ?> <span class="mono muted"><?= e(($i['node_ip'] ?? '') . ':' . ($i['node_port'] ?? '')) ?></span> <?= (int)$i['node_status'] === 1 ? '<span class="badge green"><span class="dot"></span>在线</span>' : '<span class="badge red">离线</span>' ?></dd>
        <dt>规格</dt><dd><?= (int)$i['cpu'] ?> 核 / <?= (int)$i['memory_mb'] ?> MB / <?= (int)$i['disk_gb'] ?> GB</dd>
        <dt>IP</dt><dd class="mono"><?= e(($l['ip'] ?? $i['ip']) ?: '-') ?></dd>
        <dt>用量</dt><dd>CPU <?= (float)($l['cpu_usage'] ?? $i['cpu_usage']) ?>% · 内存 <?= (int)($l['mem_usage_mb'] ?? $i['mem_usage_mb']) ?> MB</dd>
        <dt>登录用户</dt><dd><?= e($l['os_user'] ?? 'root') ?></dd>
        <dt>密码</dt><dd class="mono"><?= e($l['root_password'] ?? '-') ?></dd>
        <dt>存储池</dt><dd class="mono"><?= e($l['storage_pool'] ?? '-') ?></dd>
        <dt>创建时间</dt><dd><?= e($i['created_at']) ?></dd>
      </div>
      <?php if (!$l): ?><div class="form-hint mt-2">未能从节点获取实时详情（节点离线或该实例在节点不存在）。</div><?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h2>电源操作</h2></div>
    <div class="card-body">
      <div class="flex mb-2">
        <?php foreach ([['start','开机','success'],['stop','关机','ghost'],['reboot','重启','ghost']] as [$act,$label,$cls]): ?>
        <form class="inline-form" method="post" action="/admin/instances/<?= (int)$i['id'] ?>/action"><?= csrf_field() ?><input type="hidden" name="type" value="<?= $act ?>"><button class="btn sm <?= $cls ?>" type="submit"><?= $label ?></button></form>
        <?php endforeach; ?>
      </div>
      <form method="post" action="/admin/instances/<?= (int)$i['id'] ?>/action" class="mb-2">
        <?= csrf_field() ?><input type="hidden" name="type" value="reset_password">
        <label class="muted" style="font-size:12px">重置密码</label>
        <div class="flex"><input type="text" name="password" placeholder="留空自动生成" style="flex:1"><button class="btn sm ghost" type="submit">重置</button></div>
      </form>
      <form method="post" action="/admin/instances/<?= (int)$i['id'] ?>/action" class="mb-2">
        <?= csrf_field() ?><input type="hidden" name="type" value="resize">
        <label class="muted" style="font-size:12px">调整规格（CPU / 内存 / 磁盘）</label>
        <div class="flex">
          <input type="number" name="cpu" value="<?= (int)$i['cpu'] ?>" min="1" style="width:70px">
          <input type="number" name="memory_mb" value="<?= (int)$i['memory_mb'] ?>" min="128" style="width:90px">
          <input type="number" name="disk_gb" value="<?= (int)$i['disk_gb'] ?>" min="1" style="width:80px">
          <button class="btn sm ghost" type="submit">应用</button>
        </div>
      </form>
      <form method="post" action="/admin/instances/<?= (int)$i['id'] ?>/action" class="mb-2">
        <?= csrf_field() ?><input type="hidden" name="type" value="set_limits">
        <label class="muted" style="font-size:12px">带宽 / IO / CPU 上限</label>
        <div class="grid cols-2">
          <input type="number" name="inbound_mbps" value="0" min="0" placeholder="入站 Mbit">
          <input type="number" name="outbound_mbps" value="0" min="0" placeholder="出站 Mbit">
          <input type="number" name="disk_read_mbs" value="0" min="0" placeholder="磁盘读 MB/s">
          <input type="number" name="disk_write_mbs" value="0" min="0" placeholder="磁盘写 MB/s">
          <input type="number" name="cpu_allowance" value="100" min="0" max="100" placeholder="CPU 上限 %">
          <input type="number" name="processes" value="0" min="0" placeholder="最大进程数">
        </div>
        <div class="mt-2"><button class="btn sm ghost" type="submit">应用限速</button></div>
      </form>
      <form method="post" action="/admin/instances/<?= (int)$i['id'] ?>/action" class="mb-2">
        <?= csrf_field() ?><input type="hidden" name="type" value="snapshot_create"><button class="btn sm subtle" type="submit">创建快照</button>
      </form>
      <form method="post" action="/admin/instances/<?= (int)$i['id'] ?>/delete" onsubmit="return confirm('确认删除？此操作不可恢复')">
        <?= csrf_field() ?><button class="btn sm danger" type="submit">删除实例</button>
      </form>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-head"><h2>执行命令</h2></div>
  <div class="card-body">
    <form method="post" action="/admin/instances/<?= (int)$i['id'] ?>/action">
      <?= csrf_field() ?><input type="hidden" name="type" value="exec">
      <div class="form-row"><label>在实例内执行（Linux）</label><textarea name="command" placeholder="如：apt-get update -y && apt-get install -y nginx"></textarea></div>
      <button class="btn sm" type="submit">执行</button>
    </form>
  </div>
</div>

<?php if (!empty($l['events'])): ?>
<div class="card">
  <div class="card-head"><h2>节点事件</h2></div>
  <div class="card-body" style="padding:0">
    <div class="table-wrap"><table>
      <thead><tr><th>#</th><th>操作</th><th>结果</th><th>时间</th></tr></thead>
      <tbody>
      <?php foreach ($l['events'] as $e): ?>
        <tr><td class="num"><?= (int)$e['id'] ?></td><td class="mono"><?= e($e['action']) ?></td><td class="muted mono"><?= e((string)$e['result']) ?></td><td class="muted"><?= e($e['created_at']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
</div>
<?php endif; ?>

<a class="btn ghost" href="/admin/instances">返回列表</a>
