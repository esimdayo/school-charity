<?php
use App\Core\Csrf;
$orderLabel = fn ($s) => t(in_array($s, ['cancelled', 'unpaid'], true) ? "order_{$s}_s" : "order_$s");
?>
<a class="muted" href="<?= BASE_PATH ?>/admin"><?= t('back_admin') ?></a>
<div class="card narrow center">
  <p class="muted"><?= t('code') ?></p>
  <div class="code"><?= e($code) ?></div>
  <?php if (!$order): ?>
    <p class="badge warn"><?= t('no_order_code', ['code' => e($code)]) ?></p>
  <?php else: ?>
    <img class="pickup-photo" src="<?= photo_url($item['photo']) ?>" alt="">
    <h2><?= e($item['title']) ?></h2>
    <p><?= t('buyer') ?>: <strong><?= e($order['buyer_name']) ?></strong> · <?= e($order['buyer_phone']) ?></p>
    <p><?= t('paid_on', ['amount' => money($order['amount']), 'date' => fmt_date((int) $order['paid_at'])]) ?></p>
    <?php if ($order['status'] === 'paid'): ?>
      <form method="post" action="<?= BASE_PATH ?>/admin/orders/<?= (int) $order['id'] ?>/pickup">
        <?= Csrf::field() ?>
        <button class="btn big"><?= t('mark_handed') ?></button>
      </form>
    <?php else: ?>
      <p class="badge <?= $order['status'] === 'picked_up' ? 'ok' : 'warn' ?>">
        <?= e($orderLabel($order['status'])) ?>
        <?= $order['picked_up_at'] ? t('on_date', ['date' => fmt_date((int) $order['picked_up_at'])]) : '' ?>
      </p>
    <?php endif ?>
  <?php endif ?>
</div>
