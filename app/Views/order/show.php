<div class="card narrow center">
<?php if (in_array($order['status'], ['paid', 'picked_up'], true)): ?>
  <p class="badge ok"><?= $order['status'] === 'paid' ? t('paid_badge') : t('picked_badge') ?></p>
  <h1><?= e($item['title']) ?></h1>
  <p><?= t('pickup_code') ?></p>
  <div class="code"><?= e($order['pickup_code']) ?></div>
  <p><?= t('pickup_text') ?></p>
  <p class="muted small"><?= t('paid_on', ['amount' => money($order['amount']), 'date' => fmt_date((int) $order['paid_at'])]) ?></p>
<?php elseif ($order['status'] === 'awaiting'): ?>
  <meta http-equiv="refresh" content="15">
  <p class="badge warn"><?= t('order_awaiting') ?></p>
  <h1><?= t('checking_title') ?></h1>
  <p><strong><?= e($item['title']) ?></strong> · <?= money($order['amount']) ?></p>
  <p><?= t('checking_text') ?></p>
  <?php if ($order['user_id']): ?><p class="muted small"><?= t('code_in_telegram') ?></p><?php endif ?>
<?php elseif ($order['status'] === 'pending'): ?>
  <h1><?= t('waiting_title') ?></h1>
  <p><a class="btn" href="/pay/<?= e($order['provider']) ?>/<?= e($order['token']) ?>"><?= t('open_payment') ?></a></p>
<?php elseif ($order['status'] === 'unpaid'): ?>
  <h1><?= t('not_received_title') ?></h1>
  <p><?= t('not_received_text') ?></p>
  <a class="btn" href="/"><?= t('to_shop') ?></a>
<?php else: ?>
  <h1><?= t('order_cancelled') ?></h1>
  <p><?= t('no_money') ?></p>
  <a class="btn" href="/"><?= t('to_shop') ?></a>
<?php endif ?>
</div>
