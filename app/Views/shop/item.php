<?php
use App\Core\Csrf;
$donor = e($item['donor_name']) . ' (' . t('role_' . $item['donor_role']) . ($item['donor_class'] !== '' ? ', ' . e($item['donor_class']) : '') . ')';
?>
<a class="muted" href="/"><?= t('back_to_shop') ?></a>
<div class="detail">
  <img src="<?= photo_url($item['photo']) ?>" alt="<?= e($item['title']) ?>">
  <div>
    <h1><?= e($item['title']) ?></h1>
    <p class="price big"><?= money($item['price']) ?></p>
    <?php if ($item['description'] !== ''): ?><p><?= nl2br(e($item['description'])) ?></p><?php endif ?>
    <p class="muted"><a class="chip" href="/?cat=<?= e($item['category']) ?>"><?= t('cat_' . $item['category']) ?></a></p>
    <p class="muted"><?= t('donated_by', ['name' => $donor]) ?></p>

    <?php if ($item['status'] === 'available'): ?>
      <form method="post" action="/item/<?= (int) $item['id'] ?>/buy" class="stack">
        <?= Csrf::field() ?>
        <label><?= t('your_name') ?> <input name="buyer_name" required maxlength="80" autocomplete="name"></label>
        <label><?= t('phone') ?> <input name="buyer_phone" required maxlength="20" placeholder="+998 90 123 45 67" autocomplete="tel" inputmode="tel"></label>
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
