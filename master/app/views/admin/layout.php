<?php
/** 管理后台布局。@var string $content @var array $admin */
$f = flash();
$current = '/' . trim(\App\Core\Http::path(), '/');
$active = function (string $path) use ($current): string {
    if ($path === '/admin') {
        return $current === '/admin' ? 'active' : '';
    }
    return str_starts_with($current, $path) ? 'active' : '';
};
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($title ?? '控制台') . ' · ' . (config('site_name') ?: 'Incus 云控制台')) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="app">
  <aside class="rail">
    <div class="brand">
      <div class="mark">I</div>
      <div>
        <b><?= e(config('site_name') ?: 'Incus 云控制台') ?></b>
        <span>面板端</span>
      </div>
    </div>
    <nav>
      <div class="group">概览</div>
      <a href="/admin" class="<?= $active('/admin') ?>">控制台</a>

      <div class="group">资源</div>
      <a href="/admin/nodes" class="<?= $active('/admin/nodes') ?>">计算节点</a>
      <a href="/admin/instances" class="<?= $active('/admin/instances') ?>">云服务器</a>
      <a href="/admin/images" class="<?= $active('/admin/images') ?>">镜像</a>
      <a href="/admin/storage" class="<?= $active('/admin/storage') ?>">存储池</a>

      <div class="group">系统</div>
      <a href="/admin/users" class="<?= $active('/admin/users') ?>">用户</a>
      <a href="/admin/tasks" class="<?= $active('/admin/tasks') ?>">任务队列</a>
      <a href="/admin/logs" class="<?= $active('/admin/logs') ?>">操作日志</a>
      <a href="/admin/settings" class="<?= $active('/admin/settings') ?>">系统设置</a>
      <a href="/admin/about" class="<?= $active('/admin/about') ?>">关于</a>
    </nav>
    <div class="rail-foot">Master v<?= e(\App\Core\Version::MASTER) ?></div>
  </aside>
  <div class="main">
    <div class="topbar">
      <div>
        <div class="crumb">面板端</div>
        <div class="title"><?= e($title ?? '') ?></div>
      </div>
      <div class="who">
        <span><?= e($admin['nickname'] ?: $admin['username']) ?></span>
        <a class="btn ghost sm" href="/admin/logout">退出</a>
      </div>
    </div>
    <div class="content">
      <?php if ($f): ?>
        <div class="alert <?= e($f['type']) ?>"><?= e($f['message']) ?></div>
      <?php endif; ?>
      <?= $content ?>
    </div>
  </div>
</div>
</body>
</html>
