<form method="post" action="/admin/users">
  <?= csrf_field() ?>
  <div class="card">
    <div class="card-head"><h2>客户信息</h2></div>
    <div class="card-body">
      <div class="grid cols-2">
        <div class="form-row">
          <label>邮箱</label>
          <input type="email" name="email" required>
        </div>
        <div class="form-row">
          <label>昵称</label>
          <input type="text" name="nickname">
        </div>
        <div class="form-row">
          <label>密码</label>
          <input type="password" name="password" minlength="6" required>
          <div class="form-hint">至少 6 位，用于用户面板登录（可选）</div>
        </div>
        <div class="form-row">
          <label>备注</label>
          <input type="text" name="remark">
        </div>
      </div>
    </div>
  </div>
  <div class="form-actions">
    <button class="btn" type="submit">保存</button>
    <a class="btn ghost" href="/admin/users">返回</a>
  </div>
</form>
