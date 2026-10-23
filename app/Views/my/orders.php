<?php
use App\Core\Csrf;
use App\Models\User;
$label = fn ($s) => t(in_array($s, ['cancelled', 'unpaid'], true) ? "order_{$s}_s" : "order_$s");
?>
<div class="admin-head">
  <h1><?= t('my_orders_title') ?></h1>
  <form method="post" action="/logout"><?= Csrf::field() ?><button class="btn ghost small"><?= t('log_out') ?></button></form>
</div>
<p class="muted"><?= t('logged_in_as', ['name' => e(User::displayName($user))]) ?> <a href="/profile"><?= t('nav_profile') ?></a></p>
<?php if ($orders): ?>
  <div class="checks card">
    <?php foreach ($orders as $o): ?>
      <a class="check my-order" href="/order/<?= e($o['token']) ?>">
        <img class="thumb" src="<?= photo_url($o['photo']) ?>" alt="">
        <div class="check-info">
          <strong><?= e($o['title']) ?></strong>
          <span class="muted small"><?= money($o['amount']) ?> · <?= fmt_date((int) $o['created_at']) ?></span>
        </div>
        <?php if ($o['status'] === 'paid'): ?>
          <span class="badge ok"><?= t('pickup_code') ?>: <?= e($o['pickup_code']) ?></span>
        <?php else: ?>
          <span class="badge<?= $o['status'] === 'awaiting' || $o['status'] === 'pending' ? ' warn' : '' ?>"><?= e($label($o['status'])) ?></span>
        <?php endif ?>
      </a>
    <?php endforeach ?>
  </div>
<?php else: ?>
  <div class="empty">
    <p><?= t('no_my_orders') ?></p>
    <a class="btn" href="/"><?= t('to_shop') ?></a>
  </div>
<?php endif ?>
