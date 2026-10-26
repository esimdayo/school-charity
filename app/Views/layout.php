<?php
use App\Core\{App, Buyer, Lang};
$siteName = (string) App::config('site_name', 'School Charity Shop');
$back = urlencode($_SERVER['REQUEST_URI'] ?? '/');
$buyer = Buyer::current();
// The intro (two hands reaching for each other) plays on the main page, once per visit.
$intro = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) === '/';
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
<?php if ($intro): ?>
  // Skip the intro if it already played in this visit or the visitor asked for less motion.
  try { if (sessionStorage.getItem('intro') || matchMedia('(prefers-reduced-motion: reduce)').matches) document.documentElement.classList.add('no-intro'); } catch (e) {}
<?php endif ?>
</script>
</head>
<body>
<?php if ($intro): ?>
<div class="intro" id="intro" role="presentation">
  <svg class="intro-hands" viewBox="-10 -40 420 200" aria-hidden="true">
    <defs><filter id="intro-s" x="-20%" y="-20%" width="140%" height="140%"><feMorphology in="SourceAlpha" operator="dilate" radius="5" result="d"/><feFlood flood-color="#fff"/><feComposite in2="d" operator="in" result="w"/><feDropShadow in="w" dx="0" dy="3" stdDeviation="3" flood-color="#000" flood-opacity=".18" result="ws"/><feMerge><feMergeNode in="ws"/><feMergeNode in="SourceGraphic"/></feMerge></filter></defs>
    <g class="intro-left"><g filter="url(#intro-s)">
      <rect x="-2" y="56" width="92" height="40" rx="10" fill="#2ab3a6" transform="rotate(-8 45 76)"/>
      <g fill="#f2c29b"><rect x="80" y="50" width="62" height="38" rx="16" transform="rotate(-8 110 70)"/><rect x="118" y="44" width="54" height="11" rx="5.5" transform="rotate(-14 130 50)"/><rect x="122" y="55" width="52" height="11" rx="5.5" transform="rotate(-6 130 60)"/><rect x="122" y="66" width="46" height="11" rx="5.5" transform="rotate(2 130 70)"/><rect x="118" y="77" width="38" height="10" rx="5" transform="rotate(10 125 80)"/><rect x="100" y="22" width="12" height="40" rx="6" transform="rotate(38 106 58)"/></g>
    </g></g>
    <g class="intro-right"><g filter="url(#intro-s)">
      <rect x="310" y="56" width="92" height="40" rx="10" fill="#ffb627" transform="rotate(8 355 76)"/>
      <g fill="#b9784f"><rect x="258" y="50" width="62" height="38" rx="16" transform="rotate(8 290 70)"/><rect x="228" y="44" width="54" height="11" rx="5.5" transform="rotate(14 270 50)"/><rect x="226" y="55" width="52" height="11" rx="5.5" transform="rotate(6 270 60)"/><rect x="232" y="66" width="46" height="11" rx="5.5" transform="rotate(-2 270 70)"/><rect x="244" y="77" width="38" height="10" rx="5" transform="rotate(-10 275 80)"/><rect x="288" y="22" width="12" height="40" rx="6" transform="rotate(-38 294 58)"/></g>
    </g></g>
    <g class="intro-heart"><g filter="url(#intro-s)"><path d="M200 6c-10-14-34-9-30 9 3 13 30 27 30 27s27-14 30-27c4-18-20-23-30-9z" fill="#e0573c"/></g></g>
    <g class="intro-rays" stroke="#ffb627" stroke-width="5" stroke-linecap="round"><path d="M164 -4l-10-10M236 -4l10-10M200 -14v-14"/></g>
  </svg>
  <p class="intro-text"><?= t('intro_text') ?></p>
  <button type="button" class="intro-skip" id="intro-skip"><?= t('intro_skip') ?></button>
</div>
<?php endif ?>
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
<div class="loader" id="loader" aria-hidden="true"><div class="loader-ring"></div></div>
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
  (() => {
    // A spinner when the next page takes a moment (slow internet or server).
    // It shows only if the page hasn't loaded within 400 ms.
    const loader = document.getElementById('loader');
    let timer = 0;
    const start = () => { clearTimeout(timer); timer = setTimeout(() => loader.classList.add('on'), 400); };
    const stop = () => { clearTimeout(timer); loader.classList.remove('on'); };
    document.addEventListener('submit', (e) => {
      if (!e.defaultPrevented && e.target.target !== '_blank') start();
    });
    document.addEventListener('click', (e) => {
      const a = e.target.closest('a[href]');
      if (!a || e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
      if (a.target === '_blank' || a.hasAttribute('download') || a.origin !== location.origin) return;
      if (a.pathname === location.pathname && a.search === location.search && a.hash) return;
      start();
    });
    window.addEventListener('pageshow', stop); // coming back with the Back button
  })();
<?php if ($intro): ?>
  (() => {
    const intro = document.getElementById('intro');
    if (!intro || document.documentElement.classList.contains('no-intro')) return intro && intro.remove();
    try { sessionStorage.setItem('intro', '1'); } catch (e) {}
    const done = () => intro.remove();
    intro.addEventListener('animationend', (e) => { if (e.target === intro) done(); });
    intro.addEventListener('click', () => intro.classList.add('intro-out'));
  })();
<?php endif ?>
</script>
</body>
</html>
