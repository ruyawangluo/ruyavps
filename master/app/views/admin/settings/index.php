<?php $settings = $settings ?? []; ?>
<form method="post" action="/admin/settings">
  <?= csrf_field() ?>
  <div class="card">
    <div class="card-head"><h2>站点</h2></div>
    <div class="card-body">
      <div class="grid cols-2">
        <div class="form-row">
          <label>站点名称</label>
          <input type="text" name="site_name" value="<?= e($settings['site_name'] ?? '') ?>">
        </div>
        <div class="form-row">
          <label>站点地址</label>
          <input type="text" name="site_url" value="<?= e($settings['site_url'] ?? '') ?>">
          <div class="form-hint">用于被控端回连与生成一键安装命令</div>
        </div>
        <div class="form-row">
          <label>默认计算节点</label>
          <select name="default_node_id">
            <option value="0">自动调度</option>
            <?php foreach ($nodes ?? [] as $n): ?>
              <option value="<?= (int)$n['id'] ?>" <?= (string)($settings['default_node_id'] ?? '0') === (string)$n['id'] ? 'selected' : '' ?>><?= e($n['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <label>任务轮询超时（秒）</label>
          <input type="number" name="task_poll_timeout" value="<?= e($settings['task_poll_timeout'] ?? '30') ?>" min="5">
        </div>
      </div>
    </div>
  </div>
  <div class="form-actions"><button class="btn" type="submit">保存设置</button></div>
</form>

<div class="card">
  <div class="card-head">
    <h2>开放 API（供财务 / 前台系统调用）</h2>
    <?php if (!empty($settings['openapi_key'])): ?>
    <form method="post" action="/admin/settings/openapi" onsubmit="return confirm('重新生成后，旧的凭据立即失效，需同步更新上游系统。确定？')">
      <?= csrf_field() ?>
      <button class="btn sm ghost" type="submit">重新生成</button>
    </form>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <?php if (empty($settings['openapi_key'])): ?>
      <p class="muted" style="margin-top:0">开放 API 尚未启用。生成一组平台凭据后，上层系统即可调用 <code>/api/v1/open/*</code> 下发用户与开通机器。</p>
      <form method="post" action="/admin/settings/openapi">
        <?= csrf_field() ?>
        <button class="btn" type="submit">生成开放 API 凭据</button>
      </form>
    <?php else: ?>
      <div class="kv">
        <dt>API Key</dt><dd class="mono"><?= e($settings['openapi_key']) ?></dd>
        <dt>API Secret</dt><dd class="mono"><?= e($settings['openapi_secret']) ?></dd>
      </div>
      <div class="form-hint mt-2">签名串：<code>api_key + "\n" + timestamp + "\n" + sha256(body)</code>，签名值：<code>hmac_sha256(secret, 签名串)</code>；请求头 <code>X-Api-Key / X-Timestamp / X-Signature</code>。接口清单见「关于与更新」或 README。</div>
    <?php endif; ?>
  </div>
</div>
