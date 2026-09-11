<?php
/**
 * Quadro generale e coda di moderazione.
 *
 * @var \Noblogs\Core\View $this
 * @var array<string,int>  $stats
 * @var list<array{blog:\Noblogs\Models\Blog,owner_email:string,owner_created:string,posts:int,excerpt:string,titles:list<string>}> $queue
 * @var list<array<string,mixed>> $recentLog
 * @var \Noblogs\Models\User $staff
 */

use Noblogs\Core\Csrf;
use Noblogs\Core\I18n;
use Noblogs\Core\Url;
use Noblogs\Models\Media;
use Noblogs\Support\Dates;

$this->layout('layouts/admin');

$number = static fn(int $value): string => number_format($value, 0, ',', '.');
$since = static fn(?string $value): string => Dates::since(Dates::parse($value), I18n::locale());

$this->start('title');
echo __('admin.dashboard.title');
$this->end();

?>

<div class="admin-stats">
  <div class="admin-stat">
    <div class="admin-stat__value"><?= e($number($stats['blogs'])) ?></div>
    <div class="admin-stat__label"><?= e(__('admin.dashboard.stat.blogs')) ?></div>
  </div>
  <div class="admin-stat<?= $stats['pending'] > 0 ? ' admin-stat--alert' : '' ?>">
    <div class="admin-stat__value"><?= e($number($stats['pending'])) ?></div>
    <div class="admin-stat__label"><?= e(__('admin.dashboard.stat.pending')) ?></div>
  </div>
  <div class="admin-stat">
    <div class="admin-stat__value"><?= e($number($stats['users'])) ?></div>
    <div class="admin-stat__label"><?= e(__('admin.dashboard.stat.users')) ?></div>
  </div>
  <div class="admin-stat">
    <div class="admin-stat__value"><?= e($number($stats['posts'])) ?></div>
    <div class="admin-stat__label"><?= e(__('admin.dashboard.stat.posts')) ?></div>
  </div>
  <div class="admin-stat">
    <div class="admin-stat__value"><?= e($number($stats['reads'])) ?></div>
    <div class="admin-stat__label"><?= e(__('admin.dashboard.stat.reads')) ?></div>
  </div>
  <div class="admin-stat">
    <div class="admin-stat__value"><?= e(Media::humanBytes($stats['storage'])) ?></div>
    <div class="admin-stat__label"><?= e(__('admin.dashboard.stat.storage')) ?></div>
  </div>
</div>

<p class="admin-hint">
  <?= e(__('admin.dashboard.secondary', [
      'hidden'  => $number($stats['hidden']),
      'flagged' => $number($stats['flagged']),
      'staff'   => $number($stats['staff']),
  ])) ?>
</p>

<h2 class="admin-section-title"><?= e(__('admin.dashboard.queue.title')) ?></h2>
<p class="admin-intro"><?= e(__('admin.dashboard.queue.intro')) ?></p>

<?php if ($queue === []): ?>
  <p class="admin-empty"><?= e(__('admin.dashboard.queue.empty')) ?></p>
<?php else: ?>
  <div class="admin-queue">
    <?php foreach ($queue as $entry): ?>
      <?php $blog = $entry['blog']; ?>
      <article class="admin-queue__item<?= $blog->dodginess_score >= 1.0 ? ' admin-queue__item--risky' : '' ?>">
        <div class="admin-queue__head">
          <h3><?= e($blog->title) ?></h3>
          <a class="admin-mono" href="<?= e(Url::blogRoot($blog)) ?>" target="_blank" rel="noopener noreferrer"><?= e((string) preg_replace('#^https?://#', '', Url::blogRoot($blog))) ?></a>
          <span class="admin-tag"><?= e($blog->use_subdomain
              ? __('admin.blogs.state.subdomain')
              : __('admin.blogs.state.path')) ?></span>
          <?php if ($blog->flagged): ?><span class="admin-tag admin-tag--danger"><?= e(__('admin.blogs.state.flagged')) ?></span><?php endif; ?>
          <?php if ($blog->hidden): ?><span class="admin-tag"><?= e(__('admin.blogs.state.hidden')) ?></span><?php endif; ?>
        </div>

        <p class="admin-queue__meta">
          <span><?= e(__('admin.dashboard.queue.owner', ['email' => $entry['owner_email']])) ?></span>
          <span><?= e(__('admin.dashboard.queue.created', ['since' => $since($blog->created_at)])) ?></span>
          <span><?= e($entry['posts'] === 1
              ? __('admin.dashboard.queue.posts_one')
              : __('admin.dashboard.queue.posts', ['count' => $entry['posts']])) ?></span>
          <span class="admin-score"><?= e(__('admin.dashboard.queue.score', ['score' => number_format($blog->dodginess_score, 2, ',', '.')])) ?></span>
        </p>

        <?php if ($entry['excerpt'] !== ''): ?>
          <p class="admin-excerpt"><?= e($entry['excerpt']) ?></p>
        <?php else: ?>
          <p class="admin-empty"><?= e(__('admin.dashboard.queue.no_content')) ?></p>
        <?php endif; ?>

        <?php if ($entry['titles'] !== []): ?>
          <ul class="admin-titles">
            <?php foreach ($entry['titles'] as $title): ?>
              <li><?= e($title) ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>

        <?php
        $queueActions = ['approva'];
        if (Url::pathFallbackActive()) {
            $queueActions[] = $blog->use_subdomain ? 'solo-percorso' : 'terzo-livello';
        }
        $queueActions = array_merge($queueActions, ['nascondi', 'segnala', 'elimina']);
        $this->partial('admin/_blog-actions', [
            'blog'    => $blog,
            'actions' => $queueActions,
        ]);
        ?>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<h2 class="admin-section-title"><?= e(__('admin.maintenance.title')) ?></h2>
<div class="admin-card">
  <p><?= e(__('admin.maintenance.intro')) ?></p>
  <p class="admin-hint"><?= e(__('admin.maintenance.cron_hint')) ?></p>
  <form method="post" action="<?= e(Url::to('/admin/manutenzione')) ?>">
    <?= Csrf::field() ?>
    <button type="submit" class="admin-btn admin-btn--primary"><?= e(__('admin.maintenance.run')) ?></button>
  </form>
</div>

<h2 class="admin-section-title"><?= e(__('admin.dashboard.recent_log')) ?></h2>
<?php if ($recentLog === []): ?>
  <p class="admin-empty"><?= e(__('admin.log.empty')) ?></p>
<?php else: ?>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <tbody>
      <?php foreach ($recentLog as $entry): ?>
        <tr>
          <td class="is-quiet"><?= e($since((string) $entry['created_at'])) ?></td>
          <td><?= e((string) ($entry['actor_email'] ?? __('admin.log.system'))) ?></td>
          <td class="admin-mono"><?= e((string) $entry['action']) ?></td>
          <td><?= e((string) ($entry['subdomain'] ?? '')) ?></td>
          <td><?= e((string) ($entry['note'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p><a href="<?= e(Url::to('/admin/registro')) ?>"><?= e(__('admin.dashboard.full_log')) ?></a></p>
<?php endif; ?>
