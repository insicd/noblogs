<?php
/**
 * Elenco dei blog dell'utente.
 *
 * @var \Noblogs\Core\View   $this
 * @var \Noblogs\Models\User $user
 * @var list<array<string,mixed>> $blogs
 * @var bool                 $canCreate
 * @var int                  $maxBlogs
 * @var int                  $blogCount
 */

use Noblogs\Core\Url;
use Noblogs\Support\Dates;

$this->layout('layouts/dashboard');
?>
<header class="dash-head">
  <h1><?= e(__('dashboard.your_blogs')) ?></h1>
  <p class="dash-head-note"><?= e(__('dashboard.blog_quota', ['used' => $blogCount, 'max' => $maxBlogs])) ?></p>
</header>

<?php if ($blogs === []): ?>
  <div class="dash-empty">
    <h2><?= e(__('dashboard.no_blogs_title')) ?></h2>
    <p><?= e(__('dashboard.no_blogs_body')) ?></p>
    <?php if ($canCreate): ?>
      <p><a class="button primary" href="<?= e(Url::to('/dashboard/nuovo-blog')) ?>"><?= e(__('dashboard.create_blog')) ?></a></p>
    <?php elseif (!$user->hasVerifiedEmail()): ?>
      <p><?= e(__('dashboard.email_not_verified')) ?></p>
    <?php endif; ?>
  </div>
<?php else: ?>
  <ul class="card-list">
    <?php foreach ($blogs as $entry): ?>
      <?php /** @var \Noblogs\Models\Blog $blog */ $blog = $entry['blog']; ?>
      <li class="card">
        <h2 class="card-title">
          <a href="<?= e(Url::to('/dashboard/' . $blog->subdomain)) ?>"><?= e($blog->title) ?></a>
        </h2>
        <p class="card-address">
          <a href="<?= e(Url::blogRoot($blog)) ?>" rel="noopener"><?= e(Url::blogRoot($blog)) ?></a>
        </p>

        <dl class="stat-row">
          <div>
            <dt><?= e(__('dashboard.stat.posts')) ?></dt>
            <dd><?= e((string) $entry['posts']) ?></dd>
          </div>
          <div>
            <dt><?= e(__('dashboard.stat.pages')) ?></dt>
            <dd><?= e((string) $entry['pages']) ?></dd>
          </div>
          <div>
            <dt><?= e(__('dashboard.stat.reads_7')) ?></dt>
            <dd><?= $entry['reads'] === null ? '—' : e((string) $entry['reads']) ?></dd>
          </div>
          <div>
            <dt><?= e(__('dashboard.stat.updated')) ?></dt>
            <dd>
              <?php if ($entry['updated'] !== null): ?>
                <time datetime="<?= e($entry['updated']->format('c')) ?>">
                  <?= e(__('dashboard.ago', ['time' => Dates::since($entry['updated'], $user->locale)])) ?>
                </time>
              <?php else: ?>
                —
              <?php endif; ?>
            </dd>
          </div>
        </dl>

        <p class="card-actions">
          <a href="<?= e(Url::to('/dashboard/' . $blog->subdomain . '/articoli/nuovo')) ?>"><?= e(__('dashboard.new_post')) ?></a>
          <a href="<?= e(Url::to('/dashboard/' . $blog->subdomain . '/articoli')) ?>"><?= e(__('dashboard.nav.posts')) ?></a>
          <a href="<?= e(Url::to('/dashboard/' . $blog->subdomain . '/statistiche')) ?>"><?= e(__('dashboard.nav.analytics')) ?></a>
          <a href="<?= e(Url::to('/dashboard/' . $blog->subdomain . '/impostazioni')) ?>"><?= e(__('dashboard.nav.settings')) ?></a>
        </p>

        <?php $this->partial('dashboard/partials/pending-review', ['blog' => $blog]); ?>
      </li>
    <?php endforeach; ?>
  </ul>

  <?php if ($canCreate): ?>
    <p><a class="button" href="<?= e(Url::to('/dashboard/nuovo-blog')) ?>"><?= e(__('dashboard.create_blog')) ?></a></p>
  <?php else: ?>
    <p class="note"><?= e(__('blog.create.limit_reached', ['max' => $maxBlogs])) ?></p>
  <?php endif; ?>
<?php endif; ?>
