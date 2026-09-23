<?php /** @var bool $sshReady @var ?array $result */ ?>
<div class="card">
  <div class="card-head"><h2>通过 SSH 安装节点</h2></div>
  <div class="card-body">
    <?php if (!$sshReady): ?>
      <div class="alert error">当前 PHP 未启用 <code>ssh2</code> 扩展，无法从面板自动安装。请在 1Panel 的 PHP 设置中安装 <code>ssh2</code> 扩展，或改用「计算节点」页复制安装命令在节点上手动执行。</div>
    <?php else: ?>
      <p class="muted" style="margin-top:0">填写目标机 SSH 信息，面板会登录目标机执行节点端安装脚本，并从输出中解析出 <b>IP / 端口 / KEY</b> 自动接入。目标机需为 Ubuntu/Debian 且可被面板访问。</p>
    <?php endif; ?>
    <form method="post" action="/admin/nodes/ssh">
      <?= csrf_field() ?>
      <div class="grid cols-2">
        <div class="form-row"><label>主机地址</label><input type="text" name="host" placeholder="IP 或域名" required></div>
        <div class="form-row"><label>SSH 端口</label><input type="number" name="port" value="22" min="1"></div>
        <div class="form-row"><label>SSH 用户</label><input type="text" name="user" value="root"></div>
        <div class="form-row"><label>SSH 密码</label><input type="password" name="password" placeholder="与私钥二选一"></div>
      </div>
      <div class="form-row">
        <label>SSH 私钥（可选，填入后优先用私钥认证）</label>
        <textarea name="private_key" placeholder="-----BEGIN OPENSSH PRIVATE KEY-----"></textarea>
      </div>
      <div class="form-actions">
        <button class="btn" type="submit" <?= $sshReady ? '' : 'disabled' ?>>连接并安装</button>
        <a class="btn ghost" href="/admin/nodes">返回节点列表</a>
      </div>
    </form>
  </div>
</div>

<?php if ($result): ?>
<div class="card">
  <div class="card-head"><h2>执行结果</h2><?= $result['ok'] ? '<span class="badge green">命令已执行</span>' : '<span class="badge red">失败</span>' ?></div>
  <div class="card-body">
    <p><?= e($result['message']) ?><?= !empty($result['node_id']) ? '（已自动接入节点 #' . (int)$result['node_id'] . '）' : '' ?></p>
    <div class="muted mb-2" style="font-size:12.5px">远程输出：</div>
    <pre class="mono" style="background:#0e131a;color:#cbd5e1;border-radius:8px;padding:12px;overflow:auto;font-size:12px;max-height:380px"><?= e($result['output'] ?? '') ?></pre>
    <?php if (!empty($result['node_id'])): ?>
      <div class="form-actions"><a class="btn ghost" href="/admin/nodes/<?= (int)$result['node_id'] ?>/edit">查看该节点</a></div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>
