<?php
use App\Core\Csrf;
use App\Models\{Item, User};
$v = fn ($k) => e($user[$k] ?? '');
$new = !User::profileDone(User::find((int) $user['id']));
?>
<div class="card narrow">
  <div class="admin-head">
    <h1><?= t('profile_title') ?></h1>
    <form method="post" action="<?= BASE_PATH ?>/logout"><?= Csrf::field() ?><button class="btn ghost small"><?= t('log_out') ?></button></form>
  </div>
  <p class="muted"><?= $new ? t('profile_intro') : t('profile_tg', ['name' => e($user['name'] . ($user['username'] !== '' ? ' (@' . $user['username'] . ')' : ''))]) ?></p>
  <?php if ($saved): ?><p class="badge ok"><?= t('profile_saved') ?></p><?php endif ?>
  <?php if ($error): ?><p class="badge warn"><?= e($error) ?></p><?php endif ?>
  <form method="post" action="<?= BASE_PATH ?>/profile" class="stack">
    <?= Csrf::field() ?>
    <input type="hidden" name="back" value="<?= e($back) ?>">
    <div class="row">
      <label><?= t('first_name') ?> <input name="first_name" required maxlength="40" value="<?= $v('first_name') ?>" autocomplete="given-name"></label>
      <label><?= t('last_name') ?> <input name="last_name" required maxlength="40" value="<?= $v('last_name') ?>" autocomplete="family-name"></label>
    </div>
    <div class="row">
      <label><?= t('i_am') ?>
        <select name="role" required id="profile-role">
          <option value=""><?= t('choose_role_option') ?></option>
          <?php foreach (Item::ROLES as $r): ?>
            <option value="<?= $r ?>" <?= ($user['role'] ?? '') === $r ? 'selected' : '' ?>><?= t('role_' . $r) ?></option>
          <?php endforeach ?>
        </select>
      </label>
      <label><span id="class-label" data-student="<?= e(t('class_label')) ?>" data-other="<?= e(t('class_optional')) ?>"><?= t('class_optional') ?></span>
        <input name="class_name" id="profile-class" maxlength="20" value="<?= $v('class_name') ?>" placeholder="9B"></label>
    </div>
    <button class="btn big"><?= t($back !== '' ? 'profile_save_continue' : 'save') ?></button>
  </form>
  <?php if (!$new): ?><p class="center"><a href="<?= BASE_PATH ?>/my"><?= t('nav_my_orders') ?> →</a></p><?php endif ?>
</div>
<script>
  (() => {
    // The class is required for students only.
    const role = document.getElementById('profile-role'), cls = document.getElementById('profile-class'), label = document.getElementById('class-label');
    const update = () => { const s = role.value === 'student'; cls.required = s; label.textContent = s ? label.dataset.student : label.dataset.other; };
    role.addEventListener('change', update); update();
  })();
</script>
