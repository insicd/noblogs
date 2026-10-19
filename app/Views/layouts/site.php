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
$federated = \Noblogs\Federation\ActivityPub::blogEnabled($blog);
$apHandle = $federated ? '@' . \Noblogs\Federation\ActivityPub::handle($blog) : '';
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
<?php if ($federated): ?>
<link rel="alternate" type="application/activity+json" href="<?= e(isset($post) && $post !== null && !$post->is_page
    ? \Noblogs\Federation\ActivityPub::objectId($blog, $post)
    : \Noblogs\Federation\ActivityPub::actorId($blog)) ?>">
<?php endif; ?>

<style><?= $stylesheet ?></style>
<?php if ($federated): ?>
<style>
.ap-follow { position: relative; display: inline-block; }
.ap-follow-btn {
  display: inline-flex; align-items: center; gap: 0.35em;
  margin: 0; padding: 0; font: inherit; font-size: inherit;
  color: inherit; background: none; border: 0; cursor: pointer;
  text-decoration: underline; text-underline-offset: 0.15em;
}
.ap-follow-btn:hover { color: var(--color-link); }
.ap-follow-icon { display: block; width: 1.15em; height: 1.15em; flex: 0 0 auto; }
.ap-follow-panel {
  position: absolute; bottom: calc(100% + 0.55rem); left: 0; z-index: 8;
  width: max-content; max-width: min(22rem, calc(100vw - 2rem));
  padding: 0.7rem 0.85rem; text-align: left;
  color: var(--color-text); background: var(--color-bg);
  border: 1px solid var(--color-border); border-radius: 6px;
  box-shadow: 0 0.35rem 1.1rem rgba(0, 0, 0, 0.12);
}
.ap-follow-label {
  margin: 0 0 0.3rem; color: var(--color-muted);
  font-size: 0.8rem; letter-spacing: 0.02em;
}
.ap-follow-handle {
  display: block; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  font-size: 0.95rem; user-select: all; word-break: break-all;
}
.ap-follow-status { display: block; margin: 0.4rem 0 0; color: var(--color-accent); }
.ap-follow-hint { display: block; margin: 0.35rem 0 0; color: var(--color-muted); font-size: 0.85rem; }
</style>
<?php endif; ?>
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
    <a href="<?= e(Url::site('/feed/')) ?>">RSS</a><?php if ($federated): ?> ·
    <span class="ap-follow">
      <button type="button" class="ap-follow-btn"
              aria-expanded="false" aria-controls="ap-follow-panel"
              aria-haspopup="dialog"
              data-handle="<?= e($apHandle) ?>"
              data-copied="<?= e(__('site.fediverse.copied')) ?>">
        <?php $this->partial('partials/ap-icon', ['class' => 'ap-follow-icon']); ?>
        <?= e(__('site.fediverse.follow')) ?>
      </button>
      <span id="ap-follow-panel" class="ap-follow-panel" hidden role="dialog"
            aria-label="<?= e(__('site.fediverse.handle_label')) ?>">
        <span class="ap-follow-label"><?= e(__('site.fediverse.handle_label')) ?></span>
        <code class="ap-follow-handle"><?= e($apHandle) ?></code>
        <span class="ap-follow-status" hidden></span>
        <span class="ap-follow-hint"><?= e(__('site.fediverse.hint')) ?></span>
      </span>
    </span>
    <noscript> · <code><?= e($apHandle) ?></code></noscript>
    <?php endif; ?> ·
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
<script data-endpoint="<?= e(Url::site('/upvote')) ?>">
(() => {
  'use strict';

  const script = document.currentScript
    || document.querySelector('script[data-endpoint]');
  const widget = document.querySelector('.upvote');
  const button = widget ? widget.querySelector('[data-uid]') : null;
  if (!script || !widget || !button) {
    return;
  }

  const endpoint = (script.getAttribute('data-endpoint') || '').replace(/\/$/, '');
  const uid = button.getAttribute('data-uid') || '';
  const countEl = widget.querySelector('.upvote-count');
  let token = button.getAttribute('data-token') || '';
  let busy = false;
  let locked = false;

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

  const send = (fields) => {
    const body = new URLSearchParams(fields);
    const url = endpoint + '/' + encodeURIComponent(uid);
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { Accept: 'application/json' },
      body: body
    }).then((response) => response.json().catch(() => null));
  };

  send({ op: 'status', uid: uid }).then((data) => {
    if (locked || !data || data.error) {
      return;
    }
    if (typeof data.token === 'string' && data.token !== '') {
      token = data.token;
      button.setAttribute('data-token', data.token);
    }
    paint(!!data.voted, asCount(data.count));
  }).catch(() => {});

  button.addEventListener('click', () => {
    if (busy || button.disabled || token === '' || endpoint === '' || uid === '') {
      return;
    }
    busy = true;
    locked = true;

    const wasVoted = button.getAttribute('aria-pressed') === 'true';
    const nextVoted = !wasVoted;
    const current = asCount(countEl ? countEl.textContent : '0') ?? 0;
    paint(nextVoted, current + (nextVoted ? 1 : -1));

    send({
      op: 'set',
      uid: uid,
      token: token,
      voted: nextVoted ? '1' : '0',
      website: ''
    }).then((data) => {
      if (!data || typeof data !== 'object' || data.error) {
        paint(wasVoted, current);
        if (data && data.error) {
          button.title = String(data.error);
        }
        return;
      }
      if (typeof data.token === 'string' && data.token !== '') {
        token = data.token;
        button.setAttribute('data-token', data.token);
      }
      const count = asCount(data.count);
      paint(!!data.voted, count !== null ? count : (nextVoted ? current + 1 : current));
    }).catch(() => {
      paint(wasVoted, current);
    }).then(() => {
      busy = false;
      button.disabled = false;
    });
  });
})();
</script>
<?php endif; ?>
<script src="<?= e($js('dates.js')) ?>" defer></script>
<?php if ($federated): ?>
<script src="<?= e($js('fediverse.js')) ?>" defer></script>
<?php endif; ?>
<?= $blog->footer_directive ?? '' ?>
</body>
</html>
