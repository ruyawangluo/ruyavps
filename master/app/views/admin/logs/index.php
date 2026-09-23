<div class="card">
  <div class="card-head"><h2>操作日志</h2><span class="muted">共 <?= (int)$total ?> 条</span></div>
  <div class="card-body" style="padding:0">
    <div class="table-wrap">
    <table>
      <thead><tr><th>#</th><th>来源</th><th>操作</th><th>对象</th><th>详情</th><th>IP</th><th>时间</th></tr></thead>
      <tbody>
      <?php foreach ($logs as $l): ?>
        <tr>
          <td><?= (int)$l['id'] ?></td>
          <td><?= e($l['actor_type']) ?>#<?= (int)$l['actor_id'] ?></td>
          <td class="mono"><?= e($l['action']) ?></td>
          <td><?= e($l['target']) ?></td>
          <td class="muted mono"><?= e(mb_strimwidth((string)$l['detail'], 0, 60, '…')) ?></td>
          <td class="muted"><?= e($l['ip']) ?></td>
          <td class="muted"><?= e($l['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$logs): ?><tr><td colspan="7" class="empty">暂无日志</td></tr><?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>
<?php $pages = (int)ceil($total / max(1, $size)); if ($pages > 1): ?>
<div class="pager">
  <?php for ($i = 1; $i <= $pages; $i++): ?>
    <a class="btn sm <?= $i === $page ? '' : 'ghost' ?>" href="/admin/logs?page=<?= $i ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>
