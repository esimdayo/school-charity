<div class="card narrow center">
  <h1><?= t('thanks_title', ['name' => e($donor)]) ?></h1>
  <p><?= t('thanks_text', ['title' => e($itemTitle)]) ?></p>
  <div class="inline center-row">
    <a class="btn" href="<?= BASE_PATH ?>/donate"><?= t('donate_another') ?></a>
    <a class="btn ghost" href="<?= BASE_PATH ?>/"><?= t('to_shop') ?></a>
  </div>
</div>
