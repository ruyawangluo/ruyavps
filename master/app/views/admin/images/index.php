<?php /** @var array $nodes @var int $nodeId @var array $images @var string $warning */ ?>
<div class="between mb-2">
  <h2 style="margin:0">镜像（来自节点）</h2>
  <form method="get" action="/admin/images">
    <select name="node_id" onchange="this.form.submit()" style="min-width:240px">
      <?php foreach ($nodes as $n): ?>
        <option value="<?= (int)$n['id'] ?>" <?= (int)$n['id'] === $nodeId ? 'selected' : '' ?>><?= e($n['name']) ?> · <?= e($n['ip']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>
<?php if ($warning): ?><div class="alert info"><?= e($warning) ?></div><?php endif; ?>

<?php if (!$images): ?>
  <div class="card"><div class="card-body empty">该节点暂无镜像。可在节点上执行 <code>scripts/incus-image.sh（从 GitHub 拉取）</code> 导入。</div></div>
<?php else: ?>
<div class="tiles">
  <?php foreach ($images as $im): ?>
    <div class="tile">
      <div class="tile-mark" style="background:linear-gradient(135deg,#22c3e6,#4f8bff)"><?= (($im['type'] ?? '') === 'virtual-machine') ? 'VM' : 'IMG' ?></div>
      <div class="tile-main">
        <div class="tile-title mono"><?= e($im['name'] ?? '') ?></div>
        <div class="tile-sub"><?= e($im['type'] ?? '') === 'virtual-machine' ? '虚拟机镜像' : '容器镜像' ?> · <?= e($im['architecture'] ?? '') ?></div>
        <div class="tile-kv">
          <div>指纹 <b class="mono"><?= e($im['fingerprint'] ?? '') ?></b></div>
          <div>大小 <b><?= number_format(((int)($im['size'] ?? 0)) / 1048576, 1) ?> MB</b></div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<div class="form-hint">镜像由节点端 Incus 管理，面板只读展示。</div>
