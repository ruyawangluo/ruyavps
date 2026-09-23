<?php /** @var array $nodes @var int $nodeId @var array $pools */ ?>
<div class="between mb-2">
  <h2 style="margin:0">存储池（来自节点）</h2>
  <form method="get" action="/admin/storage">
    <select name="node_id" onchange="this.form.submit()" style="min-width:240px">
      <?php foreach ($nodes as $n): ?>
        <option value="<?= (int)$n['id'] ?>" <?= (int)$n['id'] === $nodeId ? 'selected' : '' ?>><?= e($n['name']) ?> · <?= e($n['ip']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<?php if (!$pools): ?>
  <div class="card"><div class="card-body empty">该节点暂无存储池。可在节点上执行 <code>/deploy/tools/incus-storage.sh</code> 创建。</div></div>
<?php else: ?>
<div class="tiles">
  <?php foreach ($pools as $p): ?>
    <div class="tile">
      <div class="tile-mark" style="background:linear-gradient(135deg,#17c48b,#3ad6a8)">POOL</div>
      <div class="tile-main">
        <div class="tile-title mono"><?= e(is_array($p) ? ($p['name'] ?? '') : $p) ?></div>
        <div class="tile-kv">
          <div>驱动 <b><?= e(is_array($p) ? ($p['driver'] ?? '-') : '-') ?></b></div>
          <div>状态 <b><?= e(is_array($p) ? ($p['status'] ?? '-') : '-') ?></b></div>
          <div>描述 <b><?= e(is_array($p) ? ($p['description'] ?? '') : '') ?></b></div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<div class="form-hint">存储池由节点端 Incus 管理，面板只读展示。</div>
