<?php $v = fn(string $k, $d = '') => e($node[$k] ?? $d); ?>
<form method="post" action="<?= $node ? '/admin/nodes/' . (int)$node['id'] : '/admin/nodes' ?>">
  <?= csrf_field() ?>
  <div class="card">
    <div class="card-head"><h2>节点接入信息</h2></div>
    <div class="card-body">
      <div class="grid cols-2">
        <div class="form-row">
          <label>节点名称</label>
          <input type="text" name="name" value="<?= $v('name') ?>" required>
        </div>
        <div class="form-row">
          <label>区域</label>
          <input type="text" name="region" value="<?= $v('region', '默认区域') ?>">
        </div>
        <div class="form-row">
          <label>IP / 主机名</label>
          <input type="text" name="ip" value="<?= $v('ip') ?>" placeholder="节点 IP" required>
        </div>
        <div class="form-row">
          <label>端口</label>
          <input type="number" name="port" value="<?= $v('port', '8787') ?>" min="1" max="65535">
        </div>
        <div class="form-row" style="grid-column:1/-1">
          <label>节点密钥（KEY）</label>
          <input type="text" name="node_key" value="<?= $v('node_key') ?>" class="mono" placeholder="节点安装完成后打印的 KEY" required>
        </div>
        <div class="form-row">
          <label>状态</label>
          <select name="enabled">
            <option value="1" <?= (int)($node['enabled'] ?? 1) === 1 ? 'selected' : '' ?>>启用（可调度）</option>
            <option value="0" <?= (int)($node['enabled'] ?? 1) === 0 ? 'selected' : '' ?>>停用</option>
          </select>
        </div>
        <div class="form-row">
          <label>备注</label>
          <input type="text" name="remark" value="<?= $v('remark') ?>">
        </div>
      </div>
      <div class="form-hint">面板仅保存 IP / 端口 / KEY；节点的 Incus、存储、镜像与实例数据均由节点端自身管理。</div>
    </div>
  </div>
  <div class="form-actions">
    <button class="btn" type="submit">保存</button>
    <a class="btn ghost" href="/admin/nodes">返回</a>
  </div>
</form>
