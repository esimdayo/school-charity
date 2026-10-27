<?php
use App\Core\Csrf;
use App\Controllers\StoryController;
?>
<a class="muted" href="<?= BASE_PATH ?>/admin"><?= t('back_admin') ?></a>
<h1><?= t('settings_title') ?></h1>
<?php if ($flash): ?><p class="badge <?= e($flash[0]) ?>"><?= t($flash[1]) ?></p><?php endif ?>
<?php if ($error): ?><p class="badge warn"><?= e($error) ?></p><?php endif ?>
<form method="post" action="<?= BASE_PATH ?>/admin/settings" enctype="multipart/form-data" class="card stack">
  <?= Csrf::field() ?>
  <h2><?= t('goal_label') ?></h2>
  <label><?= t('goal_amount') ?>
    <input name="goal_amount" type="number" min="0" step="1000" value="<?= $goal ?: '' ?>" placeholder="5000000">
    <span class="muted small hint"><?= t('goal_amount_hint') ?></span>
  </label>
  <h2><?= t('person_heading') ?></h2>
  <p class="muted small"><?= t('person_consent') ?></p>
  <div class="row">
    <label><?= t('person_name') ?>
      <input name="person_name" maxlength="120" value="<?= e($person['name']) ?>" placeholder="<?= e(t('person_name_placeholder')) ?>">
    </label>
    <label><?= t('person_age') ?>
      <input name="person_age" type="number" min="0" max="120" value="<?= $person['age'] ?: '' ?>">
    </label>
  </div>
  <label><?= t('person_condition') ?>
    <select name="person_condition">
      <option value=""><?= t('condition_none') ?></option>
      <?php foreach (StoryController::CONDITIONS as $c): ?>
        <option value="<?= $c ?>" <?= $person['condition'] === $c ? 'selected' : '' ?>><?= t('condition_' . $c) ?></option>
      <?php endforeach ?>
    </select>
  </label>
  <label><?= t('person_health') ?>
    <input name="person_health" maxlength="300" value="<?= e($person['health']) ?>" placeholder="<?= e(t('person_health_placeholder')) ?>">
  </label>
  <label><?= t('person_need') ?>
    <input name="person_need" maxlength="300" value="<?= e($person['need']) ?>" placeholder="<?= e(t('person_need_placeholder')) ?>">
  </label>
  <h2><?= t('story_heading') ?></h2>
  <label><?= t('story_title_label') ?>
    <input name="story_title" maxlength="120" value="<?= e($storyTitle) ?>" placeholder="<?= e(t('story_default_title')) ?>">
  </label>
  <label><?= t('story_text_label') ?>
    <textarea name="story_text" rows="10" maxlength="10000" placeholder="<?= e(t('story_text_placeholder')) ?>"><?= e($storyText) ?></textarea>
    <span class="muted small hint"><?= t('story_text_hint') ?></span>
  </label>
  <label><?= t('story_photo_label') ?>
    <?php if ($storyPhoto): ?><img class="thumb" src="<?= photo_url($storyPhoto) ?>" alt=""><?php endif ?>
    <input type="file" name="story_photo" accept="image/jpeg,image/png,image/webp">
  </label>
  <?php if ($storyPhoto): ?>
    <label class="radio"><input type="checkbox" name="remove_photo" value="1"> <?= t('story_photo_remove') ?></label>
  <?php endif ?>
  <button class="btn big"><?= t('save') ?></button>
</form>
