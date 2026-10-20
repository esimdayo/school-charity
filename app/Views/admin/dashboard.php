<?php
use App\Core\{App, Csrf};
$recipient = (string) App::config('recipient_name', '');
$providerNames = implode(', ', array_map(fn ($p) => t('provider_' . $p), $payments));
$orderLabel = fn ($s) => t(in_array($s, ['cancelled', 'unpaid'], true) ? "order_{$s}_s" : "order_$s");
?>
<div class="admin-head">
  <h1><?= t('nav_admin') ?></h1>
  <form method="post" action="/admin/logout"><?= Csrf::field() ?><button class="btn ghost small"><?= t('log_out') ?></button></form>
</div>

<?php if (in_array('test', $payments, true)): ?><p class="badge warn"><?= t('test_warning') ?></p><?php endif ?>
<?php if ($flash): ?><p class="badge <?= e($flash[0]) ?>"><?= t($flash[1]) ?></p><?php endif ?>

<div class="stats">
  <div class="card">
    <span class="muted"><?= t('stat_raised') ?></span>
    <strong><?= money($raised['total']) ?></strong>
    <?php if ($raisedSince): ?><span class="muted small"><?= t('raised_since', ['date' => fmt_date($raisedSince)]) ?></span><?php endif ?>
    <form method="post" action="/admin/raised/reset" onsubmit="return confirm(<?= e(json_encode(t('reset_confirm'))) ?>)">
      <?= Csrf::field() ?>
      <button class="btn ghost small"><?= t('reset_raised') ?></button>
    </form>
  </div>
  <div class="card"><span class="muted"><?= t('stat_check_payments') ?></span><strong><?= count($toCheck) ?></strong></div>
  <div class="card"><span class="muted"><?= t('stat_pending') ?></span><strong><?= count($pending) ?></strong></div>
  <div class="card"><span class="muted"><?= t('stat_pickup') ?></span><strong><?= $toPickUp ?></strong></div>
  <div class="card">
    <span class="muted"><?= t('stat_money_to') ?></span>
    <strong class="small"><?= e($recipient ?: t('not_set')) ?> <?= e(masked_card()) ?></strong>
    <span class="muted small"><?= t('via', ['names' => e($providerNames)]) ?></span>
  </div>
</div>

<?php if (in_array('card', $payments, true)): ?>
<section class="card">
  <h2><?= t('check_payments', ['n' => count($toCheck)]) ?></h2>
  <?php if ($toCheck): ?>
    <p class="muted small"><?= t('check_payments_help') ?></p>
    <div class="checks">
      <?php foreach ($toCheck as $o): ?>
        <div class="check">
          <img class="thumb" src="<?= photo_url($o['photo']) ?>" alt="">
          <div class="check-info">
            <strong class="price"><?= money($o['amount']) ?></strong>
            <span><?= e($o['title']) ?></span>
            <span class="muted small"><?= e($o['buyer_name']) ?> · <?= e($o['buyer_phone']) ?> · <?= t('payer_card_short', ['last4' => e($o['payer_card'])]) ?> · <?= fmt_date((int) $o['sent_at']) ?></span>
            <?php if ($o['receipt']): ?><a class="small" href="/admin/receipt/<?= (int) $o['id'] ?>" target="_blank">🧾 <?= t('receipt_view') ?></a><?php endif ?>
          </div>
          <form method="post" class="inline">
            <?= Csrf::field() ?>
            <button class="btn small" formaction="/admin/orders/<?= (int) $o['id'] ?>/confirm"><?= t('money_arrived') ?></button>
            <button class="btn ghost small" formaction="/admin/orders/<?= (int) $o['id'] ?>/unpaid"><?= t('not_received') ?></button>
          </form>
        </div>
      <?php endforeach ?>
    </div>
  <?php else: ?>
    <p class="muted"><?= t('nothing_to_check_payments') ?></p>
  <?php endif ?>
  <?php if ($telegram): ?>
    <form method="post" action="/admin/telegram/connect" class="tg-connect">
      <?= Csrf::field() ?>
      <span class="muted small"><?= t('tg_connect_help') ?></span>
      <button class="btn ghost small"><?= t('tg_connect_button') ?></button>
    </form>
  <?php endif ?>
</section>
<?php endif ?>

<section class="card">
  <h2><?= t('hand_over') ?></h2>
  <form method="get" action="/admin/pickup" class="inline">
    <input name="code" placeholder="<?= e(t('code_placeholder')) ?>" required maxlength="6" style="text-transform:uppercase">
    <button class="btn"><?= t('find') ?></button>
  </form>
</section>

<section>
  <h2><?= t('waiting_approval', ['n' => count($pending)]) ?></h2>
  <?php if ($pending): ?>
    <div class="grid">
      <?php foreach ($pending as $i): ?>
        <div class="item">
          <img src="<?= photo_url($i['photo']) ?>" alt="">
          <div class="item-body">
            <h3><?= e($i['title']) ?></h3>
            <p class="price"><?= money($i['price']) ?></p>
            <p class="muted small"><?= t('cat_' . $i['category']) ?></p>
            <p class="muted small"><?= e($i['donor_name']) ?> · <?= t('role_' . $i['donor_role']) ?> <?= e($i['donor_class']) ?></p>
            <?php if ($i['description'] !== ''): ?><p class="small"><?= e($i['description']) ?></p><?php endif ?>
            <form method="post" class="inline">
              <?= Csrf::field() ?>
              <button class="btn small" formaction="/admin/items/<?= (int) $i['id'] ?>/approve"><?= t('approve') ?></button>
              <button class="btn ghost small" formaction="/admin/items/<?= (int) $i['id'] ?>/reject"><?= t('reject') ?></button>
            </form>
          </div>
        </div>
      <?php endforeach ?>
    </div>
  <?php else: ?>
    <p class="muted"><?= t('nothing_to_check') ?></p>
  <?php endif ?>
</section>

<section>
  <h2><?= t('orders') ?></h2>
  <div class="table-wrap"><table>
    <tr><th><?= t('col_item') ?></th><th><?= t('col_buyer') ?></th><th><?= t('col_amount') ?></th><th><?= t('col_status') ?></th><th><?= t('col_code') ?></th><th><?= t('col_date') ?></th></tr>
    <?php foreach ($orders as $o): ?>
      <tr>
        <td><?= e($o['title']) ?></td>
        <td><?= e($o['buyer_name']) ?><br><span class="muted small"><?= e($o['buyer_phone']) ?></span></td>
        <td><?= money($o['amount']) ?></td>
        <td><?= e($orderLabel($o['status'])) ?><br><span class="muted small"><?= t('provider_' . $o['provider']) ?><?= $o['payer_card'] ? ' · •••• ' . e($o['payer_card']) : '' ?></span></td>
        <td><?php if ($o['pickup_code']): ?><a href="/admin/pickup?code=<?= e($o['pickup_code']) ?>"><?= e($o['pickup_code']) ?></a><?php endif ?></td>
        <td class="small"><?= fmt_date((int) $o['created_at']) ?></td>
      </tr>
    <?php endforeach ?>
    <?php if (!$orders): ?><tr><td colspan="6" class="muted"><?= t('no_orders') ?></td></tr><?php endif ?>
  </table></div>
</section>

<section>
  <h2><?= t('all_items') ?></h2>
  <div class="table-wrap"><table>
    <tr><th></th><th><?= t('col_item') ?></th><th><?= t('col_price') ?></th><th><?= t('col_status') ?></th><th></th></tr>
    <?php foreach ($items as $i): ?>
      <tr>
        <td><img class="thumb" src="<?= photo_url($i['photo']) ?>" alt=""></td>
        <td><a href="/item/<?= (int) $i['id'] ?>"><?= e($i['title']) ?></a><br><span class="muted small"><?= e($i['donor_name']) ?></span></td>
        <td><?= money($i['price']) ?></td>
        <td><?= t('status_' . $i['status']) ?></td>
        <td>
          <?php if ($i['status'] === 'available'): ?>
            <form method="post" action="/admin/items/<?= (int) $i['id'] ?>/reject"><?= Csrf::field() ?><button class="btn ghost small"><?= t('remove') ?></button></form>
          <?php elseif ($i['status'] === 'rejected'): ?>
            <form method="post" action="/admin/items/<?= (int) $i['id'] ?>/approve"><?= Csrf::field() ?><button class="btn ghost small"><?= t('put_back') ?></button></form>
          <?php endif ?>
        </td>
      </tr>
    <?php endforeach ?>
    <?php if (!$items): ?><tr><td colspan="5" class="muted"><?= t('no_items') ?></td></tr><?php endif ?>
  </table></div>
</section>
