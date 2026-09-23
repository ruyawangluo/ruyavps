<?php
/** 登录/注册布局（左右分栏）。@var string $content */
flash();
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Incus 云控制台') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="auth-wrap">
  <aside class="auth-aside">
    <div>
      <div class="mark">I</div>
      <h2>用一个控制台，管好每一台机器。</h2>
      <p>基于 Incus 的容器与虚拟机控制中间件：节点纳管、实例生命周期、快照、访问密钥与开放 API。</p>
      <div class="tags">
        <span>容器 / 虚拟机</span>
        <span>多用户与访问 Key</span>
        <span>开放 API 下发</span>
      </div>
    </div>
    <div class="rail-foot" style="color:#5d6a7a">Master v<?= e(\App\Core\Version::MASTER) ?></div>
  </aside>
  <main class="auth-main">
    <?= $content ?>
  </main>
</div>
</body>
</html>
