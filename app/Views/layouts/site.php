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

<?php if ($blog->analytics_active && $trackPath !== null): ?>
<script src="<?= e(Url::asset('js/hit.js')) ?>" data-endpoint="<?= e(Url::site('/hit')) ?>" data-uid="<?= e($trackPath) ?>" defer></script>
<?php endif; ?>
<?php if (!empty($showUpvote)): ?>
<script src="<?= e(Url::asset('js/upvote.js')) ?>" data-endpoint="<?= e(Url::site('/upvote')) ?>" data-info="<?= e(Url::site('/upvote-info/' . $post->uid)) ?>" defer></script>
<?php endif; ?>
<script src="<?= e(Url::asset('js/dates.js')) ?>" defer></script>
<?= $blog->footer_directive ?? '' ?>
</body>
</html>
