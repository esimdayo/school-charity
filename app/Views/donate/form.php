<?php
use App\Core\Csrf;
use App\Models\Item;
$v = fn ($k) => e($old[$k] ?? '');
?>
<div class="card narrow">
  <h1><?= t('donate_title') ?></h1>
  <p class="muted"><?= t('donate_intro') ?></p>
  <?php if ($error): ?><p class="badge warn"><?= e($error) ?></p><?php endif ?>
  <form method="post" action="<?= BASE_PATH ?>/donate" enctype="multipart/form-data" class="stack">
    <?= Csrf::field() ?>
    <label><?= t('photo') ?> <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" capture="environment" required></label>
    <label><?= t('what_is_it') ?> <input name="title" required maxlength="80" value="<?= $v('title') ?>" placeholder="<?= e(t('what_placeholder')) ?>"></label>
    <label><?= t('category') ?>
      <select name="category" required>
        <option value=""><?= t('choose_category_option') ?></option>
        <?php foreach (Item::CATEGORIES as $c): ?>
          <option value="<?= $c ?>" <?= ($old['category'] ?? '') === $c ? 'selected' : '' ?>><?= t('cat_' . $c) ?></option>
        <?php endforeach ?>
      </select>
    </label>
    <label><?= t('description') ?> <textarea name="description" maxlength="1000" rows="3" placeholder="<?= e(t('description_placeholder')) ?>"><?= $v('description') ?></textarea></label>
    <label><?= t('price') ?> <input name="price" type="number" min="1000" step="500" required value="<?= $v('price') ?>" placeholder="20000"></label>
    <label><?= t('your_name') ?> <input name="donor_name" required maxlength="80" value="<?= $v('donor_name') ?>"></label>
    <div class="row">
      <label><?= t('i_am') ?>
        <select name="donor_role">
          <?php foreach (Item::ROLES as $r): ?>
            <option value="<?= $r ?>" <?= ($old['donor_role'] ?? '') === $r ? 'selected' : '' ?>><?= t('role_' . $r) ?></option>
          <?php endforeach ?>
        </select>
      </label>
      <label><?= t('class_optional') ?> <input name="donor_class" maxlength="20" value="<?= $v('donor_class') ?>" placeholder="9B"></label>
    </div>
    <button class="btn big"><?= t('send_for_approval') ?></button>
  </form>
</div>
