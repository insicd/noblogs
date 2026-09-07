<?php
/**
 * Riepilogo di un blog, con i collegamenti a tutte le sezioni.
 *
 * @var \Noblogs\Core\View       $this
 * @var \Noblogs\Models\Blog     $blog
 * @var \Noblogs\Models\User     $user
 * @var string                   $address
 * @var int                      $posts
 * @var int                      $pages
 * @var int                      $drafts
 * @var int                      $scheduled
 * @var list<\Noblogs\Models\Post> $recent
 * @var int                      $files
 * @var string                   $storage
 * @var string                   $quota
 * @var int|null                 $subscribers
 * @var array{reads:int,visitors:int}|null $reads
 * @var int|null                 $readers
 */

use Noblogs\Core\Url;
use Noblogs\Support\Dates;

$base = '/dashboard/' . $blog->subdomain;

$this->layout('layouts/dashboard');
?>
<header class="dash-head">
  <h1><?= e($blog->title) ?></h1>
  <p class="dash-head-note">
    <a href="<?= e($address) ?>" rel="noopener"><?= e($address) ?></a>
  </p>
  <p class="dash-head-actions">
    <a class="button primary" href="<?= e(Url::to($base . '/articoli/nuovo')) ?>"><?= e(__('dashboard.new_post')) ?></a>
    <a class="button" href="<?= e(Url::to($base . '/articoli/nuovo?pagina=1')) ?>"><?= e(__('dashboard.new_page')) ?></a>
  </p>
</header>

<dl class="stat-grid">
  <div>
    <dt><?= e(__('dashboard.stat.posts')) ?></dt>
    <dd><a href="<?= e(Url::to($base . '/articoli')) ?>"><?= e((string) $posts) ?></a></dd>
  </div>
  <div>
    <dt><?= e(__('dashboard.stat.pages')) ?></dt>
    <dd><a href="<?= e(Url::to($base . '/pagine')) ?>"><?= e((string) $pages) ?></a></dd>
  </div>
  <div>
    <dt><?= e(__('dashboard.stat.drafts')) ?></dt>
    <dd><a href="<?= e(Url::to($base . '/articoli?stato=bozze')) ?>"><?= e((string) $drafts) ?></a></dd>
  </div>
  <div>
    <dt><?= e(__('dashboard.stat.scheduled')) ?></dt>
    <dd><a href="<?= e(Url::to($base . '/articoli?stato=programmati')) ?>"><?= e((string) $scheduled) ?></a></dd>
  </div>
  <div>
    <dt><?= e(__('dashboard.stat.reads_7')) ?></dt>
    <dd>
      <?php if ($reads === null): ?>
        <a href="<?= e(Url::to($base . '/impostazioni')) ?>">—</a>
      <?php else: ?>
        <a href="<?= e(Url::to($base . '/statistiche')) ?>"><?= e((string) $reads['reads']) ?></a>
      <?php endif; ?>
    </dd>
  </div>
  <div>
    <dt><?= e(__('dashboard.stat.visitors_7')) ?></dt>
    <dd><?= $reads === null ? '—' : e((string) $reads['visitors']) ?></dd>
  </div>
  <div>
    <dt><?= e(__('dashboard.stat.files')) ?></dt>
    <dd><a href="<?= e(Url::to($base . '/file')) ?>"><?= e((string) $files) ?></a></dd>
  </div>
  <div>
    <dt><?= e(__('dashboard.stat.storage')) ?></dt>
    <dd><?= e($storage) ?> <span class="muted">/ <?= e($quota) ?></span></dd>
  </div>
  <div>
    <dt><?= e(__('dashboard.stat.subscribers')) ?></dt>
    <dd>
      <?php if ($subscribers === null): ?>
        <a href="<?= e(Url::to($base . '/impostazioni')) ?>">—</a>
      <?php else: ?>
        <a href="<?= e(Url::to($base . '/iscritti')) ?>"><?= e((string) $subscribers) ?></a>
      <?php endif; ?>
    </dd>
  </div>
  <div>
    <dt><?= e(__('dashboard.stat.readers_now')) ?></dt>
    <dd><?= $readers === null ? '—' : e((string) $readers) ?></dd>
  </div>
</dl>

<section class="panel">
  <h2><?= e(__('dashboard.recent_posts')) ?></h2>

  <?php if ($recent === []): ?>
    <p class="note"><?= e(__('post.none_yet')) ?></p>
  <?php else: ?>
    <ul class="row-list">
      <?php foreach ($recent as $post): ?>
        <li>
          <a class="row-title" href="<?= e(Url::to($base . '/articoli/' . $post->id)) ?>"><?= e($post->title) ?></a>
          <?php $this->partial('dashboard/partials/post-status', ['post' => $post]); ?>
          <?php $published = $post->publishedAt(); ?>
          <?php if ($published !== null): ?>
            <time class="row-date" datetime="<?= e($published->format('c')) ?>">
              <?= e(Dates::format(Dates::toLocal($published, $user->timezone), 'j M Y', $user->locale)) ?>
            </time>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<section class="panel">
  <h2><?= e(__('dashboard.shortcuts')) ?></h2>
  <ul class="link-grid">
    <li><a href="<?= e(Url::to($base . '/contenuto')) ?>"><?= e(__('dashboard.nav.content')) ?></a></li>
    <li><a href="<?= e(Url::to($base . '/aspetto')) ?>"><?= e(__('dashboard.nav.theme')) ?></a></li>
    <li><a href="<?= e(Url::to($base . '/navigazione')) ?>"><?= e(__('dashboard.nav.nav')) ?></a></li>
    <li><a href="<?= e(Url::to($base . '/file')) ?>"><?= e(__('dashboard.nav.media')) ?></a></li>
    <li><a href="<?= e(Url::to($base . '/impostazioni/avanzate')) ?>"><?= e(__('settings.advanced_title')) ?></a></li>
    <li><a href="<?= e(Url::to($base . '/impostazioni/dominio')) ?>"><?= e(__('settings.domain_title')) ?></a></li>
    <li><a href="<?= e(Url::to($base . '/impostazioni/redirect')) ?>"><?= e(__('settings.redirects_title')) ?></a></li>
    <li><a href="<?= e(Url::to($base . '/importa')) ?>"><?= e(__('import.title')) ?></a></li>
    <li><a href="<?= e(Url::to($base . '/esporta')) ?>"><?= e(__('export.title')) ?></a></li>
  </ul>
</section>
