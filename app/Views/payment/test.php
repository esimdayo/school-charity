<?php use App\Core\Csrf; ?>
<div class="card narrow">
  <p class="badge warn"><?= t('test_mode') ?></p>
  <h1><?= t('pay_amount', ['amount' => money($order['amount'])]) ?></h1>
  <p><?= t('pay_for', ['title' => e($item['title'])]) ?></p>
  <form method="post" action="<?= BASE_PATH ?>/pay/test/<?= e($order['token']) ?>" class="inline">
    <?= Csrf::field() ?>
    <button class="btn" name="result" value="paid"><?= t('pay_test') ?></button>
    <button class="btn ghost" name="result" value="cancel"><?= t('cancel') ?></button>
  </form>
</div>
