<?php
/** 用户面板布局。@var string $content @var array $user */
$f = flash();
$current = '/' . trim(\App\Core\Http::path(), '/');
$active = function (string $path) use ($current): string {
    if ($path === '/user') {
        return $current === '/user' ? 'active' : '';
    }
    return str_starts_with($current, $path) ? 'active' : '';
};
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($title ?? '我的服务器') . ' · ' . (config('site_name') ?: 'Incus 云控制台')) ?></title>
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
        <span>用户中心</span>
      </div>
    </div>
    <nav>
      <div class="group">我的资源</div>
      <a href="/user" class="<?= $active('/user') ?>">概览</a>
      <a href="/user/instances" class="<?= $active('/user/instances') ?>">云服务器</a>
    </nav>
    <div class="rail-foot"><?= e($user['email']) ?></div>
  </aside>
  <div class="main">
    <div class="topbar">
      <div>
        <div class="crumb">用户中心</div>
        <div class="title"><?= e($title ?? '') ?></div>
      </div>
      <div class="who">
        <span><?= e($user['nickname'] ?: $user['email']) ?></span>
        <a class="btn ghost sm" href="/user/logout">退出</a>
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
