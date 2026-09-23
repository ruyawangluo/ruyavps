<div class="auth-card">
  <h1>注册账号</h1>
  <p class="sub">创建账号以购买和管理云服务器</p>
  <form method="post" action="/user/register">
    <?= csrf_field() ?>
    <div class="form-row">
      <label>邮箱</label>
      <input type="email" name="email" value="<?= e(old('email')) ?>" required autofocus>
    </div>
    <div class="form-row">
      <label>昵称（可选）</label>
      <input type="text" name="nickname" value="<?= e(old('nickname')) ?>">
    </div>
    <div class="form-row">
      <label>密码</label>
      <input type="password" name="password" minlength="6" required>
      <div class="form-hint">至少 6 位</div>
    </div>
    <button class="btn" type="submit">注册</button>
  </form>
  <div class="auth-links">已有账号？<a href="/user/login">去登录</a></div>
</div>
