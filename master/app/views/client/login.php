<div class="auth-card">
  <h1>用户登录</h1>
  <p class="sub">登录后管理你的云服务器</p>
  <form method="post" action="/user/login">
    <?= csrf_field() ?>
    <div class="form-row">
      <label>邮箱</label>
      <input type="email" name="email" autocomplete="username" required autofocus>
    </div>
    <div class="form-row">
      <label>密码</label>
      <input type="password" name="password" autocomplete="current-password" required>
    </div>
    <button class="btn" type="submit">登录</button>
  </form>
  <div class="auth-links">
    还没有账号？<a href="/user/register">立即注册</a> · <a href="/admin/login">管理员入口</a>
  </div>
</div>
