<?php
use App\Core\{App, Buyer, Lang};
$siteName = (string) App::config('site_name', 'School Charity Shop');
$back = urlencode($_SERVER['REQUEST_URI'] ?? '/');
$buyer = Buyer::current();
?>
<!doctype html>
<html lang="<?= Lang::current() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= e($siteName) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap">
<link rel="stylesheet" href="/style.css">
<script>
  // Apply the theme the visitor picked before the page draws, so it doesn't flash.
  try { const t = localStorage.getItem('theme'); if (t) document.documentElement.dataset.theme = t; } catch (e) {}
</script>
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
  <button type="button" class="theme-toggle" id="theme-toggle" data-dark="<?= e(t('theme_dark')) ?>" data-light="<?= e(t('theme_light')) ?>"></button>
</div>
<header class="top">
  <a class="brand" href="/"><span class="heart">♥</span> <?= e($siteName) ?></a>
  <nav>
    <a href="/"><?= t('nav_shop') ?></a>
    <?php if ($buyer): ?>
      <a href="/profile" class="nav-keep"><?= t('nav_profile') ?></a>
    <?php else: ?>
      <a href="/login?back=<?= $back ?>" class="nav-keep"><?= t('nav_login') ?></a>
    <?php endif ?>
    <a href="/donate" class="btn small"><?= t('nav_donate') ?></a>
    <?php if ($admin): ?><a href="/admin"><?= t('nav_admin') ?></a><?php endif ?>
  </nav>
</header>
<main><?= $content ?></main>
<footer>
  <?= t('footer') ?>
</footer>
<script>
  (() => {
    const root = document.documentElement, btn = document.getElementById('theme-toggle');
    const isDark = () => root.dataset.theme ? root.dataset.theme === 'dark' : matchMedia('(prefers-color-scheme: dark)').matches;
    const label = () => { btn.textContent = isDark() ? btn.dataset.light : btn.dataset.dark; };
    btn.addEventListener('click', () => {
      root.dataset.theme = isDark() ? 'light' : 'dark';
      try { localStorage.setItem('theme', root.dataset.theme); } catch (e) {}
      label();
    });
    label();
  })();
</script>
</body>
</html>
