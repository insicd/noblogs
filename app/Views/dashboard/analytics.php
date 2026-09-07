<?php
/**
 * Statistiche di lettura.
 *
 * @var \Noblogs\Core\View   $this
 * @var \Noblogs\Models\Blog $blog
 * @var \Noblogs\Models\User $user
 * @var int                  $days
 * @var list<int>            $periods
 * @var bool                 $active
 * @var list<array{date:string,reads:int,visitors:int}> $series
 * @var array{reads:int,visitors:int} $totals
 * @var int                  $readers
 * @var list<array<string,mixed>> $top
 * @var array<string,list<array{label:string,reads:int}>> $breakdown
 */

use Noblogs\Core\Url;

$base = '/dashboard/' . $blog->subdomain;

$dimensions = [
    'referrer' => __('analytics.by_referrer'),
    'device'   => __('analytics.by_device'),
    'browser'  => __('analytics.by_browser'),
    'country'  => __('analytics.by_country'),
];

$this->layout('layouts/dashboard');
?>
<header class="dash-head">
  <h1><?= e(__('analytics.title')) ?></h1>
  <p class="dash-head-note"><?= e(__('analytics.privacy_note')) ?></p>
</header>

<?php if (!$active): ?>
  <div class="notice">
    <p><?= e(__('analytics.disabled')) ?></p>
    <p><a class="button" href="<?= e(Url::to($base . '/impostazioni')) ?>"><?= e(__('analytics.enable')) ?></a></p>
  </div>
<?php else: ?>

  <ul class="filter-tabs">
    <?php foreach ($periods as $period): ?>
      <li>
        <a href="<?= e(Url::to($base . '/statistiche') . '?giorni=' . $period) ?>"
           class="<?= $days === $period ? 'current' : '' ?>">
          <?= e(__('analytics.last_days', ['days' => $period])) ?>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>

  <dl class="stat-grid">
    <div>
      <dt><?= e(__('analytics.reads')) ?></dt>
      <dd><?= e((string) $totals['reads']) ?></dd>
    </div>
    <div>
      <dt><?= e(__('analytics.visitors')) ?></dt>
      <dd><?= e((string) $totals['visitors']) ?></dd>
    </div>
    <div>
      <dt><?= e(__('analytics.readers_now')) ?></dt>
      <dd><?= e((string) $readers) ?></dd>
    </div>
    <div>
      <dt><?= e(__('analytics.daily_average')) ?></dt>
      <dd><?= e((string) (int) round($totals['reads'] / max(1, $days))) ?></dd>
    </div>
  </dl>

  <section class="panel">
    <h2><?= e(__('analytics.chart_title', ['days' => $days])) ?></h2>
    <?php $this->partial('dashboard/partials/chart', ['series' => $series, 'user' => $user]); ?>
  </section>

  <section class="panel">
    <h2><?= e(__('analytics.top_title')) ?></h2>

    <?php if ($top === []): ?>
      <p class="note"><?= e(__('analytics.no_data')) ?></p>
    <?php else: ?>
      <table class="data-table">
        <thead>
          <tr>
            <th scope="col"><?= e(__('analytics.column_page')) ?></th>
            <th scope="col" class="num"><?= e(__('analytics.reads')) ?></th>
            <th scope="col" class="num"><?= e(__('analytics.visitors')) ?></th>
            <th scope="col" class="num"><?= e(__('analytics.upvotes')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($top as $row): ?>
            <tr>
              <td>
                <?php if ($row['post_id'] > 0 && $row['slug'] !== ''): ?>
                  <a href="<?= e(Url::to($base . '/articoli/' . $row['post_id'])) ?>"><?= e($row['title']) ?></a>
                <?php else: ?>
                  <?= e($row['title']) ?>
                <?php endif; ?>
              </td>
              <td class="num"><?= e((string) $row['reads']) ?></td>
              <td class="num"><?= e((string) $row['visitors']) ?></td>
              <td class="num"><?= e((string) $row['upvotes']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>

  <div class="panel-grid">
    <?php foreach ($dimensions as $key => $label): ?>
      <section class="panel">
        <h2><?= e($label) ?></h2>
        <?php $rows = $breakdown[$key] ?? []; ?>
        <?php if ($rows === []): ?>
          <p class="note"><?= e(__('analytics.no_data')) ?></p>
        <?php else: ?>
          <?php $best = max(array_map(static fn(array $row): int => $row['reads'], $rows)); ?>
          <ul class="bar-list">
            <?php foreach ($rows as $row): ?>
              <li>
                <span class="bar-label"><?= e($row['label']) ?></span>
                <span class="bar-track">
                  <span class="bar-fill" style="width: <?= e((string) max(1, (int) round($row['reads'] / max(1, $best) * 100))) ?>%"></span>
                </span>
                <span class="bar-value"><?= e((string) $row['reads']) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
