<a class="muted" href="/"><?= t('back_to_shop') ?></a>
<article class="story">
  <h1><?= e($title) ?></h1>
  <?php if ($photo): ?><img src="<?= photo_url($photo) ?>" alt="<?= e($person['name']) ?>"><?php endif ?>

  <?php if ($person['name'] || $person['health'] || $person['condition']): ?>
    <section class="card person">
      <?php if ($person['name']): ?>
        <div class="person-row"><span class="muted small"><?= t('person_name') ?></span><strong class="person-name"><?= e($person['name']) ?></strong></div>
      <?php endif ?>
      <?php if ($person['age']): ?>
        <div class="person-row"><span class="muted small"><?= t('person_age') ?></span><span><?= t('age_years', ['n' => $person['age']]) ?></span></div>
      <?php endif ?>
      <?php if ($person['condition']): ?>
        <div class="person-row"><span class="muted small"><?= t('person_condition') ?></span><span class="badge warn"><?= t('condition_' . $person['condition']) ?></span></div>
      <?php endif ?>
      <?php if ($person['health']): ?>
        <div class="person-row"><span class="muted small"><?= t('person_health') ?></span><span><?= e($person['health']) ?></span></div>
      <?php endif ?>
      <?php if ($person['need']): ?>
        <div class="person-row"><span class="muted small"><?= t('person_need') ?></span><span><?= e($person['need']) ?></span></div>
      <?php endif ?>
    </section>
  <?php endif ?>

  <?php if ($text !== ''): ?><div class="story-text"><?= nl2br(e($text)) ?></div><?php endif ?>
  <?php if ($goal > 0): ?>
    <section class="goal" style="margin-top: 28px">
      <?php require ROOT . '/app/Views/partials/goal.php' ?>
    </section>
  <?php endif ?>
  <p class="center"><a class="btn big" href="/"><?= t('story_help_button') ?></a></p>
</article>
