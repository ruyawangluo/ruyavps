<?php /** @var string $content, $code, $message */ ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? '错误') ?></title>
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card" style="text-align:center">
    <h1 style="font-size:42px;margin-bottom:10px"><?= e($code ?? 404) ?></h1>
    <p class="sub"><?= e($message ?? '页面不存在') ?></p>
    <a class="btn" href="/">返回首页</a>
  </div>
</div>
</body>
</html>
