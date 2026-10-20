<?php
use App\Core\{App, Csrf};
$card = full_card();
$recipient = (string) App::config('recipient_name', '');
$amount = money($order['amount']);
$action = '/pay/card/' . e($order['token']);
?>
<div class="card narrow">
  <h1><?= t('card_title', ['amount' => $amount]) ?></h1>
  <p><?= t('pay_for', ['title' => e($item['title'])]) ?></p>
  <p><?= t('card_step1') ?></p>
  <div class="transfer">
    <div class="transfer-row">
      <div class="field"><span class="muted small"><?= t('card_number') ?></span><strong class="cardno"><?= e($card) ?></strong></div>
      <button type="button" class="btn ghost small" data-copy="<?= e(str_replace(' ', '', $card)) ?>"><?= t('copy') ?></button>
    </div>
    <?php if ($recipient !== ''): ?>
      <div class="field"><span class="muted small"><?= t('card_owner') ?></span><strong><?= e($recipient) ?></strong></div>
    <?php endif ?>
    <div class="transfer-row">
      <div class="field"><span class="muted small"><?= t('amount') ?></span><strong><?= $amount ?></strong></div>
      <button type="button" class="btn ghost small" data-copy="<?= (int) $order['amount'] ?>"><?= t('copy') ?></button>
    </div>
  </div>
  <p class="muted small"><?= t('exact_amount') ?> <?= t('card_time', ['min' => $minutes]) ?></p>
  <p><?= t('card_step2') ?></p>
  <?php if ($error): ?><p class="badge warn"><?= e($error) ?></p><?php endif ?>
  <form method="post" action="<?= $action ?>" enctype="multipart/form-data" class="stack">
    <?= Csrf::field() ?>
    <label><?= t('payer_card') ?>
      <input name="last4" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" required placeholder="1234">
      <span class="muted small hint"><?= t('payer_card_hint') ?></span>
    </label>
    <label><?= t('receipt_label') ?>
      <input type="file" name="receipt" accept="image/jpeg,image/png,image/webp">
      <span class="muted small hint"><?= t('receipt_hint') ?></span>
    </label>
    <button class="btn big" name="result" value="sent"><?= t('sent_button') ?></button>
  </form>
  <form method="post" action="<?= $action ?>">
    <?= Csrf::field() ?>
    <button class="btn ghost small" name="result" value="cancel"><?= t('cancel_order') ?></button>
  </form>
</div>
<script>
  document.querySelectorAll('[data-copy]').forEach((b) => b.addEventListener('click', () => {
    navigator.clipboard?.writeText(b.dataset.copy).then(() => { b.textContent = <?= json_encode(t('copied')) ?>; }).catch(() => {});
  }));
</script>
