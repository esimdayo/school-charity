<?php
use App\Core\App;
$recipient = (string) App::config('recipient_name', '');
$who = $recipient !== '' ? '<strong>' . e($recipient) . '</strong>' : t('hero_someone');
?>
<section class="hero">
  <div>
    <h1><?= t('hero_title') ?></h1>
    <p><?= t('hero_text', ['name' => $who]) ?></p>
    <p class="muted"><?= t('hero_pickup') ?></p>
  </div>
  <div class="raised">
    <span class="muted"><?= t('raised') ?></span>
    <strong><?= money($raised['total']) ?></strong>
    <span class="muted"><?= t('items_sold', ['n' => (int) $raised['count']]) ?></span>
  </div>
</section>

<?php if ($items): ?>
  <div class="grid">
    <?php foreach ($items as $item): ?>
      <a class="item" href="/item/<?= (int) $item['id'] ?>">
        <img src="<?= photo_url($item['photo']) ?>" alt="<?= e($item['title']) ?>" loading="lazy">
        <div class="item-body">
          <h3><?= e($item['title']) ?></h3>
          <p class="price"><?= money($item['price']) ?></p>
          <p class="muted"><?= t('from', ['name' => e($item['donor_name']) . ($item['donor_class'] !== '' ? ', ' . e($item['donor_class']) : '')]) ?></p>
        </div>
      </a>
    <?php endforeach ?>
  </div>
<?php else: ?>
  <div class="empty">
    <p><?= t('empty_shop') ?></p>
    <a class="btn" href="/donate"><?= t('donate_first') ?></a>
  </div>
<?php endif ?>
