<?php use App\Core\{App, Csrf}; ?>
<div class="card narrow">
  <h1><?= t('admin_login') ?></h1>
  <?php if ($error): ?><p class="badge warn"><?= t('wrong_password') ?></p><?php endif ?>
  <?php if ((string) App::config('admin_password', '') === ''): ?><p class="badge warn"><?= t('set_password') ?></p><?php endif ?>
  <form method="post" action="<?= BASE_PATH ?>/admin/login" class="stack">
    <?= Csrf::field() ?>
    <label><?= t('password') ?> <input type="password" name="password" required autofocus></label>
    <button class="btn"><?= t('log_in') ?></button>
  </form>
</div>
