<div class="card narrow center">
  <h1><?= e($title) ?></h1>
  <p><?= e($text) ?></p>
  <?php if (!empty($link)): ?><a class="btn" href="<?= e($link[0]) ?>"><?= e($link[1]) ?></a><?php endif ?>
</div>
