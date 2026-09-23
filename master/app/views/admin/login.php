<div class="auth-card">
  <h1>管理后台登录</h1>
  <p class="sub">Incus 云管理平台 · 主控端</p>
  <form method="post" action="/admin/login">
    <?= csrf_field() ?>
    <div class="form-row">
      <label>用户名</label>
      <input type="text" name="username" autocomplete="username" required autofocus>
    </div>
    <div class="form-row">
      <label>密码</label>
      <input type="password" name="password" autocomplete="current-password" required>
    </div>
    <button class="btn" type="submit">登录</button>
  </form>
  <div class="auth-links"><a href="/user/login">前往用户中心</a></div>
</div>
