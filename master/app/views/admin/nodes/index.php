<?php
/** @var array $nodes */
$installCmd = \App\Controllers\DeployController::nodeInstallCommand();
$incusTools = [
    ['incus-manage', '安装 / 卸载 Incus（含网桥与默认 profile）'],
    ['incus-storage', '创建 / 删除 / 列出存储池（ZFS / Btrfs）'],
    ['incus-image', '下载并导入 LXC / VM 镜像到本地'],
];
?>
<div class="between mb-2">
  <h2 style="margin:0">计算节点</h2>
  <div class="flex">
    <a class="btn ghost" href="/admin/nodes/ssh">SSH 安装节点</a>
    <a class="btn" href="/admin/nodes/create">接入节点</a>
  </div>
</div>

<details class="card">
  <summary style="padding:16px 20px;cursor:pointer;font-weight:700">节点准备：安装命令与 Incus 工具（在节点上手动执行）</summary>
  <div class="card-body" style="border-top:1px solid var(--line-2)">
    <div class="form-row">
      <label>① 安装节点端程序（被控程序 + 本地库 + 本地 API）</label>
      <div class="flex">
        <input type="text" readonly class="mono" id="node-install-cmd" value="<?= e($installCmd) ?>">
        <button type="button" class="btn sm ghost" onclick="navigator.clipboard.writeText(document.getElementById('node-install-cmd').value)">复制</button>
      </div>
      <div class="form-hint">执行完成后会打印节点的 <b>IP / 端口 / KEY</b>，用「接入节点」填入即可。</div>
    </div>
    <div class="form-row">
      <label>② Incus 单独配置（安装 / 存储池 / 镜像）</label>
      <?php foreach ($incusTools as [$tool, $desc]): ?>
        <div class="flex" style="margin-bottom:6px">
          <input type="text" readonly class="mono" id="tool-<?= e($tool) ?>" value="<?= e(\App\Controllers\DeployController::toolCommand($tool)) ?>">
          <button type="button" class="btn sm ghost" onclick="navigator.clipboard.writeText(document.getElementById('tool-<?= e($tool) ?>').value)">复制</button>
        </div>
        <div class="form-hint" style="margin-bottom:8px"><?= e($desc) ?></div>
      <?php endforeach; ?>
    </div>
  </div>
</details>

<?php if (!$nodes): ?>
  <div class="card"><div class="card-body empty">还没有节点。请先在节点上执行上面的安装命令，再用「接入节点」填入 IP/端口/KEY。</div></div>
<?php else: ?>
<div class="tiles">
  <?php foreach ($nodes as $n): ?>
    <div class="tile">
      <div class="tile-mark"><?= e(mb_substr($n['name'], 0, 1)) ?></div>
      <div class="tile-main">
        <div class="tile-title"><?= e($n['name']) ?> <?= node_status_badge((int)$n['status']) ?> <?= (int)$n['enabled'] === 1 ? '' : '<span class="badge gray">停用</span>' ?></div>
        <div class="tile-sub mono"><?= e($n['ip']) ?>:<?= (int)$n['port'] ?></div>
        <div class="tile-kv">
          <div>区域 <b><?= e($n['region']) ?></b></div>
          <div>Incus <b class="mono"><?= e($n['incus_version'] ?: '-') ?></b></div>
          <div>节点程序 <b class="mono"><?= e($n['agent_version'] ?: '-') ?></b></div>
          <div>最后探测 <b><?= e($n['last_seen'] ?: '从未') ?></b></div>
        </div>
      </div>
      <div class="tile-actions">
        <form class="inline-form" method="post" action="/admin/nodes/<?= (int)$n['id'] ?>/test"><?= csrf_field() ?><button class="btn sm ghost" type="submit">测试</button></form>
        <a class="btn sm ghost" href="/admin/nodes/<?= (int)$n['id'] ?>/edit">编辑</a>
        <form class="inline-form" method="post" action="/admin/nodes/<?= (int)$n['id'] ?>/toggle"><?= csrf_field() ?><button class="btn sm ghost" type="submit"><?= (int)$n['enabled'] === 1 ? '停用' : '启用' ?></button></form>
        <form class="inline-form" method="post" action="/admin/nodes/<?= (int)$n['id'] ?>/delete" onsubmit="return confirm('确认移除该节点？')"><?= csrf_field() ?><button class="btn sm danger" type="submit">移除</button></form>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
