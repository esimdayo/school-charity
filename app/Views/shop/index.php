<?php
use App\Models\Item;
$link = fn (string $cat) => '/?' . http_build_query(array_filter(['cat' => $cat, 'q' => $search]));
?>
<section class="hero">
  <div>
    <h1><?= t('hero_title') ?></h1>
    <p><?= t('hero_text') ?></p>
    <p class="muted"><?= t('hero_pickup') ?></p>
  </div>
  <div class="raised">
    <span class="muted"><?= t('raised') ?></span>
    <strong><?= money($raised['total']) ?></strong>
    <span class="muted"><?= t('items_sold', ['n' => (int) $raised['count']]) ?></span>
  </div>
</section>

<?php if ($items || $anyItems): ?>
  <form method="get" action="/" class="search">
    <?php if ($category !== ''): ?><input type="hidden" name="cat" value="<?= e($category) ?>"><?php endif ?>
    <input type="search" name="q" value="<?= e($search) ?>" placeholder="<?= e(t('search_placeholder')) ?>" aria-label="<?= e(t('search_placeholder')) ?>">
    <button class="btn"><?= t('search_button') ?></button>
  </form>
  <nav class="chips" aria-label="<?= e(t('category')) ?>">
    <a class="chip<?= $category === '' ? ' active' : '' ?>" href="<?= e($link('')) ?>"><?= t('cat_all') ?></a>
    <?php foreach (Item::CATEGORIES as $c): ?>
      <a class="chip<?= $category === $c ? ' active' : '' ?>" href="<?= e($link($c)) ?>"><?= t('cat_' . $c) ?></a>
    <?php endforeach ?>
  </nav>
<?php endif ?>

<?php if ($items): ?>
  <div class="grid">
    <?php foreach ($items as $item): ?>
      <a class="item" href="/item/<?= (int) $item['id'] ?>">
        <img src="<?= photo_url($item['photo']) ?>" alt="<?= e($item['title']) ?>" loading="lazy">
        <div class="item-body">
          <h3><?= e($item['title']) ?></h3>
          <p class="price"><?= money($item['price']) ?></p>
          <p class="muted small"><?= t('cat_' . $item['category']) ?></p>
          <p class="muted"><?= t('from', ['name' => e($item['donor_name']) . ($item['donor_class'] !== '' ? ', ' . e($item['donor_class']) : '')]) ?></p>
        </div>
      </a>
    <?php endforeach ?>
  </div>
<?php elseif ($anyItems): ?>
  <div class="empty">
    <p><?= t('no_results') ?></p>
    <a class="btn ghost" href="/"><?= t('show_all') ?></a>
  </div>
<?php else: ?>
  <div class="empty">
    <p><?= t('empty_shop') ?></p>
    <a class="btn" href="/donate"><?= t('donate_first') ?></a>
  </div>
<?php endif ?>
<script>
  // On phones the category row scrolls sideways: keep the chosen one in view.
  document.querySelector('.chip.active')?.scrollIntoView({ block: 'nearest', inline: 'center' });
</script>
