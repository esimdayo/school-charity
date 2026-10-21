<?php
$maxCat = max(1, ...array_column($categories, 'raised'));
$maxWeek = max(1, ...array_column($weeks, 'raised'));
?>
<a class="muted" href="/admin"><?= t('back_admin') ?></a>
<h1><?= t('stats_title') ?></h1>
<p class="muted small"><?= t('stats_all_time') ?></p>

<div class="stats">
  <div class="card"><span class="muted"><?= t('stats_raised') ?></span><strong><?= money($totals['raised']) ?></strong></div>
  <div class="card"><span class="muted"><?= t('stats_sold') ?></span><strong><?= (int) $totals['sold'] ?></strong></div>
  <div class="card"><span class="muted"><?= t('stats_average') ?></span><strong><?= money((int) round($totals['average'])) ?></strong></div>
  <div class="card"><span class="muted"><?= t('stats_on_sale') ?></span><strong><?= $totals['on_sale'] ?></strong></div>
  <div class="card"><span class="muted"><?= t('stats_donors') ?></span><strong><?= $totals['donors'] ?></strong></div>
</div>

<section class="card chart-card">
  <h2><?= t('stats_weekly') ?></h2>
  <p class="muted small"><?= t('stats_weekly_hint') ?></p>
  <div class="cols">
    <?php foreach ($weeks as $w): ?>
      <div class="col" tabindex="0">
        <span class="col-tip"><?= fmt_week($w['start']) ?>: <?= money($w['raised']) ?> · <?= t('items_sold', ['n' => $w['sold']]) ?></span>
        <div class="col-fill" style="height: <?= $w['raised'] ? max(2, round($w['raised'] * 100 / $maxWeek)) : 0 ?>%"></div>
      </div>
    <?php endforeach ?>
  </div>
  <div class="col-labels">
    <?php foreach ($weeks as $w): ?><span><?= date('d.m', $w['start']) ?></span><?php endforeach ?>
  </div>
</section>

<section class="card chart-card">
  <h2><?= t('stats_by_category') ?></h2>
  <div class="hbars">
    <?php foreach ($categories as $cat => $c): ?>
      <div class="hbar" title="<?= e(t('cat_' . $cat)) ?>: <?= e(money($c['raised'])) ?>, <?= e(t('items_sold', ['n' => $c['sold']])) ?>">
        <span class="small"><?= t('cat_' . $cat) ?></span>
        <div class="hbar-track"><div class="hbar-fill" style="width: <?= $c['raised'] ? max(1, round($c['raised'] * 100 / $maxCat)) : 0 ?>%"></div></div>
        <span class="hbar-value"><?= money($c['raised']) ?> <span class="muted">· <?= $c['sold'] ?></span></span>
      </div>
    <?php endforeach ?>
  </div>
</section>

<section class="chart-card">
  <h2><?= t('stats_top_donors') ?></h2>
  <div class="table-wrap"><table>
    <tr><th>#</th><th><?= t('col_donor') ?></th><th><?= t('col_donated') ?></th><th><?= t('col_sold') ?></th><th><?= t('col_raised') ?></th></tr>
    <?php foreach ($donors as $n => $d): ?>
      <tr>
        <td class="muted"><?= $n + 1 ?></td>
        <td><?= e($d['donor_name']) ?> <span class="muted small">· <?= t('role_' . $d['donor_role']) ?></span></td>
        <td><?= (int) $d['donated'] ?></td>
        <td><?= (int) $d['sold'] ?></td>
        <td><strong><?= money($d['raised']) ?></strong></td>
      </tr>
    <?php endforeach ?>
    <?php if (!$donors): ?><tr><td colspan="5" class="muted"><?= t('no_items') ?></td></tr><?php endif ?>
  </table></div>
</section>
