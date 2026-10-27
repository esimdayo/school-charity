<?php
use App\Core\Csrf;
use App\Models\User;
$donor = e($item['donor_name']) . ' (' . t('role_' . $item['donor_role']) . ($item['donor_class'] !== '' ? ', ' . e($item['donor_class']) : '') . ')';
?>
<a class="muted" href="<?= BASE_PATH ?>/"><?= t('back_to_shop') ?></a>
<div class="detail">
  <img src="<?= photo_url($item['photo']) ?>" alt="<?= e($item['title']) ?>">
  <div>
    <h1><?= e($item['title']) ?></h1>
    <p class="price big"><?= money($item['price']) ?></p>
    <?php if ($item['description'] !== ''): ?><p><?= nl2br(e($item['description'])) ?></p><?php endif ?>
    <p class="muted"><a class="chip" href="<?= BASE_PATH ?>/?cat=<?= e($item['category']) ?>"><?= t('cat_' . $item['category']) ?></a></p>
    <p class="muted"><?= t('donated_by', ['name' => $donor]) ?></p>

    <?php if ($item['status'] === 'available' && !$buyer): ?>
      <div class="stack">
        <a class="btn big tg-btn" href="<?= BASE_PATH ?>/login?back=<?= urlencode('/item/' . (int) $item['id']) ?>"><?= t('login_to_buy') ?></a>
        <p class="muted small"><?= t('login_to_buy_note') ?></p>
      </div>
    <?php elseif ($item['status'] === 'available'): ?>
      <form method="post" action="<?= BASE_PATH ?>/item/<?= (int) $item['id'] ?>/buy" class="stack">
        <?= Csrf::field() ?>
        <p class="badge"><?= t('buying_as', ['name' => e(User::displayName($buyer))]) ?></p>
        <?php if (count($payments) > 1): ?>
          <fieldset>
            <legend><?= t('pay_with') ?></legend>
            <?php foreach ($payments as $i => $p): ?>
              <label class="radio"><input type="radio" name="provider" value="<?= e($p) ?>" <?= $i === 0 ? 'checked' : '' ?>> <?= t('provider_' . $p) ?></label>
            <?php endforeach ?>
          </fieldset>
        <?php else: ?>
          <input type="hidden" name="provider" value="<?= e($payments[0]) ?>">
        <?php endif ?>
        <button class="btn big"><?= t('buy_for', ['price' => money($item['price'])]) ?></button>
        <p class="muted small"><?= t('buy_note') ?></p>
      </form>
    <?php else: ?>
      <p class="badge"><?= $item['status'] === 'sold' ? t('sold_note') : t('reserved_note') ?></p>
    <?php endif ?>
  </div>
</div>
