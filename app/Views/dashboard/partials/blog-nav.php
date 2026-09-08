<?php
/**
 * Navigazione del blog aperto nel pannello.
 *
 * @var \Noblogs\Models\Blog $blog
 * @var string               $section
 */

use Noblogs\Core\Url;

$base = '/dashboard/' . $blog->subdomain;

$sections = [
    'overview'    => ['', __('dashboard.nav.overview')],
    'posts'       => ['/articoli', __('dashboard.nav.posts')],
    'pages'       => ['/pagine', __('dashboard.nav.pages')],
    'theme'       => ['/aspetto', __('dashboard.nav.theme')],
    'nav'         => ['/navigazione', __('dashboard.nav.nav')],
    'analytics'   => ['/statistiche', __('dashboard.nav.analytics')],
    'media'       => ['/file', __('dashboard.nav.media')],
    'subscribers' => ['/iscritti', __('dashboard.nav.subscribers')],
    'settings'    => ['/impostazioni', __('dashboard.nav.settings')],
];

$tools = [
    'content' => ['/contenuto', __('dashboard.nav.content')],
    'import'  => ['/importa', __('dashboard.nav.import')],
];
?>
<nav class="dash-side" aria-label="<?= e(__('dashboard.nav.label')) ?>">
  <p class="dash-side-title">
    <a href="<?= e(Url::to($base)) ?>"><?= e($blog->title) ?></a>
    <a class="dash-side-visit" href="<?= e(Url::blogRoot($blog)) ?>" rel="noopener"><?= e(__('dashboard.visit')) ?> ↗</a>
  </p>

  <ul>
    <?php foreach ($sections as $key => [$path, $label]): ?>
      <li><a href="<?= e(Url::to($base . $path)) ?>"
             class="<?= $section === $key ? 'current' : '' ?>"
             <?= $section === $key ? 'aria-current="page"' : '' ?>><?= e($label) ?></a></li>
    <?php endforeach; ?>
  </ul>

  <p class="dash-side-group"><?= e(__('dashboard.nav.tools')) ?></p>
  <ul>
    <?php foreach ($tools as $key => [$path, $label]): ?>
      <li><a href="<?= e(Url::to($base . $path)) ?>"
             class="<?= $section === $key ? 'current' : '' ?>"
             <?= $section === $key ? 'aria-current="page"' : '' ?>><?= e($label) ?></a></li>
    <?php endforeach; ?>
    <li><a href="<?= e(Url::to($base . '/esporta')) ?>"><?= e(__('dashboard.nav.export')) ?></a></li>
  </ul>

  <?php $this->partial('dashboard/partials/pending-review', ['blog' => $blog, 'class' => 'dash-side-note']); ?>
</nav>
