<div class="card">
  <div class="card-head"><h2>客户管理</h2><a class="btn" href="/admin/users/create">添加客户</a></div>
  <div class="card-body" style="padding:0">
    <div class="table-wrap">
    <table>
      <thead><tr><th>ID</th><th>邮箱</th><th>昵称</th><th>实例数</th><th>状态</th><th>注册时间</th><th>最后登录</th><th>操作</th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td class="num"><?= (int)$u['id'] ?></td>
          <td><?= e($u['email']) ?></td>
          <td><?= e($u['nickname']) ?></td>
          <td class="num"><?= (int)\App\Core\Db::value("SELECT COUNT(*) FROM instances WHERE user_id = ? AND status <> 'deleted'", [$u['id']]) ?></td>
          <td><?= (int)$u['status'] === 1 ? '<span class="badge green">正常</span>' : '<span class="badge red">禁用</span>' ?></td>
          <td class="muted"><?= e($u['created_at']) ?></td>
          <td class="muted"><?= e($u['last_login_at'] ?: '-') ?></td>
          <td>
            <form class="inline-form" method="post" action="/admin/users/<?= (int)$u['id'] ?>/toggle">
              <?= csrf_field() ?>
              <button class="btn sm ghost" type="submit"><?= (int)$u['status'] === 1 ? '禁用' : '启用' ?></button>
            </form>
            <form class="inline-form" method="post" action="/admin/users/<?= (int)$u['id'] ?>/delete" onsubmit="return confirm('确认删除该客户？')">
              <?= csrf_field() ?>
              <button class="btn sm danger" type="submit">删除</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$users): ?><tr><td colspan="8" class="empty">暂无客户</td></tr><?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>
