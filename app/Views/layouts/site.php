<?php
/**
 * Guscio di ogni pagina pubblica di un blog.
 *
 * Il foglio di stile è incorporato invece che collegato: una richiesta HTTP in
 * meno e nessun momento in cui la pagina appare senza formattazione.
 *
 * @var \Noblogs\Models\Blog $blog
 * @var \Noblogs\Core\View   $this
 */

use Noblogs\Core\Config;
use Noblogs\Core\Url;
use Noblogs\Support\Str;

/** @var string $stylesheet */
/** @var string $navHtml */
/** @var string $titleHtml */
/** @var string $pageTitle */
/** @var string $description */
/** @var string $canonical */
/** @var string|null $metaImage */
/** @var bool $indexable */
/** @var string $bodyClass */
/** @var string|null $trackPath */

$lang = $lang ?? $blog->displayLang();
$faviconIsGlyph = $blog->faviconIsGlyph();
?>
<!doctype html>
<html lang="<?= e($lang) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<?php if ($description !== ''): ?>
<meta name="description" content="<?= e(Str::limit($description, 300, '')) ?>">
<?php endif; ?>
<link rel="canonical" href="<?= e($canonical) ?>">
<?php if (!$indexable): ?>
<meta name="robots" content="noindex, nofollow">
<?php endif; ?>

<?php if ($faviconIsGlyph): ?>
<link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y=".9em" font-size="90">' . $blog->favicon . '</text></svg>') ?>">
<?php else: ?>
<link rel="icon" href="<?= e($blog->favicon) ?>">
<?php endif; ?>

<meta property="og:type" content="<?= isset($post) && $post !== null && !$post->is_page ? 'article' : 'website' ?>">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:site_name" content="<?= e($blog->title) ?>">
<?php if ($description !== ''): ?>
<meta property="og:description" content="<?= e(Str::limit($description, 300, '')) ?>">
<?php endif; ?>
<?php if (!empty($metaImage)): ?>
<meta property="og:image" content="<?= e($metaImage) ?>">
<meta name="twitter:card" content="summary_large_image">
<?php else: ?>
<meta name="twitter:card" content="summary">
<?php endif; ?>

<link rel="alternate" type="application/atom+xml" title="<?= e($blog->title) ?>" href="<?= e(Url::site('/feed/')) ?>">

<style><?= $stylesheet ?></style>
<?= $blog->header_directive ?? '' ?>
</head>
<body class="<?= e($bodyClass) ?>">

<header class="site-header">
  <h1 class="site-title"><a href="<?= e(Url::site('/')) ?>"><?= $titleHtml ?></a></h1>
  <?php if ($navHtml !== ''): ?>
  <nav class="site-nav" aria-label="<?= e(__('site.nav_label')) ?>"><?= $navHtml ?></nav>
  <?php endif; ?>
</header>

<main>
<?= $this->slot('content') ?>
</main>

<footer class="site-footer">
  <p>
    <?php if (!$blog->isIndexable()): ?>
      <span class="site-notice"><?= e(__('site.pending_review')) ?></span> ·
    <?php endif; ?>
    <a href="<?= e(Url::site('/feed/')) ?>">RSS</a> ·
    <a href="<?= e(Url::platform('/')) ?>"><?= e(Config::get('site.name', 'Noblogs')) ?></a>
  </p>
</footer>

<?php
$js = static function (string $file): string {
    $path = NOBLOGS_PUBLIC . '/assets/js/' . $file;
    $url = Url::asset('js/' . $file);
    return $url . '?v=' . (is_file($path) ? (string) filemtime($path) : NOBLOGS_VERSION);
};
?>
<?php if ($blog->analytics_active && $trackPath !== null): ?>
<script src="<?= e($js('hit.js')) ?>" data-endpoint="<?= e(Url::site('/hit')) ?>" data-uid="<?= e($trackPath) ?>" defer></script>
<?php endif; ?>
<?php if (!empty($showUpvote)): ?>
<script data-endpoint="<?= e(Url::site('/upvote')) ?>" data-info="<?= e(Url::site('/upvote') . '?uid=' . rawurlencode($post->uid)) ?>">
(() => {
  'use strict';

  const script = document.currentScript
    || document.querySelector('script[data-endpoint][data-info]');
  const widget = document.querySelector('.upvote');
  const button = widget ? widget.querySelector('[data-uid]') : null;

  if (!script || !widget || !button) {
    return;
  }

  const endpoint = script.getAttribute('data-endpoint');
  if (!endpoint) {
    return;
  }

  const infoUrl = script.getAttribute('data-info');
  const countEl = widget.querySelector('.upvote-count');
  let token = button.getAttribute('data-token') || '';
  let busy = false;

  const asCount = (value) => {
    const n = parseInt(String(value), 10);
    return Number.isFinite(n) ? n : null;
  };

  const paint = (voted, count) => {
    button.setAttribute('aria-pressed', voted ? 'true' : 'false');
    widget.classList.toggle('is-voted', voted);
    if (countEl && count !== null) {
      countEl.textContent = String(Math.max(0, count));
    }
    widget.hidden = false;
    button.disabled = false;
  };

  const apply = (data) => {
    if (!data || typeof data !== 'object') {
      return false;
    }
    if (data.enabled === false || data.error === 'disabled') {
      widget.hidden = true;
      button.disabled = true;
      return false;
    }
    if (data.error) {
      return false;
    }
    if (typeof data.token === 'string' && data.token !== '') {
      token = data.token;
      button.setAttribute('data-token', data.token);
    }
    paint(!!data.voted, asCount(data.count));
    return true;
  };

  const request = (url, options) => fetch(url, Object.assign({
    credentials: 'same-origin',
    cache: 'no-store',
    headers: { Accept: 'application/json' }
  }, options)).then((response) => response.json().catch(() => null));

  if (infoUrl) {
    const info = infoUrl + (infoUrl.indexOf('?') === -1 ? '?' : '&') + '_=' + Date.now();
    request(info).then(apply).catch(() => {});
  }

  button.addEventListener('click', () => {
    if (busy || button.disabled || token === '' || !endpoint) {
      return;
    }
    busy = true;
    button.disabled = true;

    const wasVoted = button.getAttribute('aria-pressed') === 'true';
    const nextVoted = !wasVoted;
    const current = asCount(countEl ? countEl.textContent : '0') ?? 0;
    paint(nextVoted, current + (nextVoted ? 1 : -1));

    const body = new URLSearchParams();
    body.set('uid', button.getAttribute('data-uid') || '');
    body.set('token', token);
    body.set('interacted', '1');
    body.set('website', '');

    request(endpoint, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: body.toString()
    }).then((data) => {
      if (!apply(data)) {
        paint(wasVoted, current);
      }
    }).catch(() => {
      paint(wasVoted, current);
    }).then(() => {
      busy = false;
      if (!widget.hidden) {
        button.disabled = false;
      }
    });
  });
})();
</script>
<?php endif; ?>
<script src="<?= e($js('dates.js')) ?>" defer></script>
<?= $blog->footer_directive ?? '' ?>
</body>
</html>
