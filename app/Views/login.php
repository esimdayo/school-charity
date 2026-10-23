<div class="card narrow center login">
  <div class="tg-logo" aria-hidden="true">✈</div>
  <h1><?= t('login_title') ?></h1>
  <p><?= t('login_text') ?></p>
  <a class="btn big tg-btn" href="<?= e($link) ?>" target="_blank" rel="noopener"><?= t('login_button') ?></a>
  <ol class="login-steps">
    <li><?= t('login_step1') ?></li>
    <li><?= t('login_step2', ['bot' => '@' . e($bot)]) ?></li>
    <li><?= t('login_step3') ?></li>
  </ol>
  <p class="muted small" id="login-status"><?= t('login_waiting') ?></p>
</div>
<script>
  (() => {
    const status = document.getElementById('login-status');
    const check = () => fetch('/login/check', { credentials: 'same-origin' })
      .then((r) => r.json())
      .then((d) => {
        if (d.status === 'ok') { location.href = d.redirect; return; }
        if (d.status === 'expired') { status.textContent = <?= json_encode(t('login_expired')) ?>; setTimeout(() => location.reload(), 1500); return; }
        setTimeout(check, 2000);
      })
      .catch(() => setTimeout(check, 4000));
    setTimeout(check, 2000);
  })();
</script>
