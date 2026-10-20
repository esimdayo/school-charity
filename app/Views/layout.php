<?php
use App\Core\{App, Lang};
$siteName = (string) App::config('site_name', 'School Charity Shop');
$recipient = (string) App::config('recipient_name', '');
$back = urlencode($_SERVER['REQUEST_URI'] ?? '/');
?>
<!doctype html>
<html lang="<?= Lang::current() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= e($siteName) ?></title>
<link rel="stylesheet" href="/style.css">
</head>
<body>
<div class="langbar">
  <?php foreach (Lang::NAMES as $code => $name): ?>
    <?php if ($code === Lang::current()): ?>
      <strong><?= $name ?></strong>
    <?php else: ?>
      <a href="/lang/<?= $code ?>?back=<?= $back ?>" lang="<?= $code ?>"><?= $name ?></a>
    <?php endif ?>
  <?php endforeach ?>
</div>
<header class="top">
  <a class="brand" href="/"><span class="heart">♥</span> <?= e($siteName) ?></a>
  <nav>
    <a href="/"><?= t('nav_shop') ?></a>
    <a href="/donate" class="btn small"><?= t('nav_donate') ?></a>
    <?php if ($admin): ?><a href="/admin"><?= t('nav_admin') ?></a><?php endif ?>
  </nav>
</header>
<main><?= $content ?></main>
<footer>
  <?= t('footer') ?>
  <?php if ($recipient): ?><?= t('footer_recipient', ['name' => e($recipient) . ' ' . e(masked_card())]) ?><?php endif ?>
</footer>
</body>
</html>
