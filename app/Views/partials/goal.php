<?php
// Fundraising goal bar. Needs $goal (so'm) and $raised (Order::totalRaised()).
$percent = $goal > 0 ? min(100, (int) floor($raised['total'] * 100 / $goal)) : 0;
?>
<div class="goal-head">
  <span class="muted"><?= t('goal_label') ?></span>
  <span><strong><?= money($raised['total']) ?></strong> <span class="muted"><?= t('goal_of', ['goal' => money($goal)]) ?></span></span>
</div>
<div class="goal-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $percent ?>" aria-label="<?= e(t('goal_label')) ?>">
  <span style="width: <?= $percent ?>%"></span>
</div>
<span class="muted small"><?= $percent >= 100 ? t('goal_reached') : t('goal_percent', ['n' => $percent]) ?> · <?= t('items_sold', ['n' => (int) $raised['count']]) ?></span>
