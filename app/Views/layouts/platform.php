<?php
/**
 * Guscio delle pagine pubbliche e di accesso della piattaforma.
 *
 * Il foglio di stile è collegato, non incorporato come nei blog: qui le pagine
 * sono molte e cambiano poco, quindi conviene che il browser lo tenga in cache.
 *
 * @var \Noblogs\Core\View        $this
 * @var \Noblogs\Core\Tenant|null $tenant
 */

use Noblogs\Core\Auth;
use Noblogs\Core\Config;
use Noblogs\Core\Csrf;
use Noblogs\Core\I18n;
use Noblogs\Core\Session;
use Noblogs\Core\Url;
use Noblogs\Support\Str;

$siteName = (string) ($siteName ?? Config::get('site.name', 'Noblogs'));
$pageTitle = (string) ($pageTitle ?? $siteName);
$description = (string) ($description ?? Config::get('site.tagline', ''));
$bodyClass = (string) ($bodyClass ?? 'page');
$canonical = (string) ($canonical ?? '');
$indexable = (bool) ($indexable ?? true);

// Sessione e utente si toccano solo sul dominio principale: se questo guscio
// finisse per servire una pagina di errore sull'host di un blog, aprire la
// sessione ci farebbe mandare un cookie a un lettore che non ne ha chiesto uno.
$onPlatform = !isset($tenant) || $tenant === null || $tenant->isPlatform();
$currentUser = $onPlatform ? Auth::user() : null;
$flashes = $onPlatform ? Session::takeFlash() : [];

// Url::to() produce percorsi relativi all'host corrente: fuori dal dominio
// principale (una pagina di errore servita sull'host di un blog) servono
// indirizzi assoluti, altrimenti «Esplora» punterebbe dentro il blog.
$link = static fn(string $path): string => $onPlatform ? Url::to($path) : Url::platform($path);

$navItems = [
    '/esplora'      => __('platform.nav.discover'),
    '/aiuto'        => __('platform.nav.help'),
    '/informazioni' => __('platform.nav.about'),
];
?>
<!doctype html>
<html lang="<?= e(I18n::locale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<?php if ($description !== ''): ?>
<meta name="description" content="<?= e(Str::limit($description, 300, '')) ?>">
<?php endif; ?>
<?php if ($canonical !== ''): ?>
<link rel="canonical" href="<?= e($canonical) ?>">
<?php endif; ?>
<?php if (!$indexable): ?>
<meta name="robots" content="noindex, nofollow">
<?php endif; ?>
<link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y=".9em" font-size="88">✍️</text></svg>') ?>">
<link rel="alternate" type="application/atom+xml" title="<?= e($siteName . ' — ' . __('discover.title')) ?>" href="<?= e(Url::platform('/esplora/feed')) ?>">
<link rel="stylesheet" href="<?= e(Url::asset('css/platform.css')) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e($siteName) ?>">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<?php if ($canonical !== ''): ?>
<meta property="og:url" content="<?= e($canonical) ?>">
<?php endif; ?>
<?php if ($description !== ''): ?>
<meta property="og:description" content="<?= e(Str::limit($description, 300, '')) ?>">
<?php endif; ?>
<meta name="twitter:card" content="summary">
</head>
<body class="<?= e($bodyClass) ?>">

<a class="skip-link" href="#contenuto"><?= e(__('platform.skip')) ?></a>

<header class="pf-header">
  <div class="pf-shell">
    <a class="pf-logo" href="<?= e($link('/')) ?>">
      <span class="pf-logo-mark" aria-hidden="true">✍</span><?= e($siteName) ?>
    </a>

    <nav class="pf-nav" aria-label="<?= e(__('platform.nav.label')) ?>">
      <ul>
        <?php foreach ($navItems as $path => $label): ?>
          <li><a href="<?= e($link($path)) ?>"><?= e($label) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </nav>

    <div class="pf-account">
      <?php if ($currentUser !== null): ?>
        <a class="pf-button" href="<?= e($link('/dashboard')) ?>"><?= e(__('platform.nav.dashboard')) ?></a>
        <form class="pf-logout" method="post" action="<?= e($link('/esci')) ?>">
          <?= Csrf::field() ?>
          <button type="submit" class="pf-link-button"><?= e(__('auth.logout.submit')) ?></button>
        </form>
      <?php else: ?>
        <a class="pf-link" href="<?= e($link('/accedi')) ?>"><?= e(__('platform.nav.login')) ?></a>
        <a class="pf-button" href="<?= e($link('/registrati')) ?>"><?= e(__('platform.nav.register')) ?></a>
      <?php endif; ?>
    </div>
  </div>
</header>

<?php if ($flashes !== []): ?>
<div class="pf-shell">
  <ul class="pf-flash" role="status" aria-label="<?= e(__('platform.flash.label')) ?>">
    <?php foreach ($flashes as $flash): ?>
      <li class="pf-flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<main id="contenuto" class="pf-main">
<?php
// Nota sullo slot 'content': View::renderTemplate() assegna a questo slot
// l'output diretto del template, sovrascrivendo quello che il template aveva
// messo con start('content')/end(). Perciò le viste della piattaforma, dopo
// end(), rimandano in output il proprio slot: è l'unico modo perché il
// contenuto arrivi fin qui senza modificare View.
echo $this->slot('content');
?>
</main>

<footer class="pf-footer">
  <div class="pf-shell">
    <nav aria-label="<?= e(__('platform.nav.label')) ?>">
      <ul class="pf-footer-links">
        <li><a href="<?= e($link('/privacy')) ?>"><?= e(__('platform.footer.privacy')) ?></a></li>
        <li><a href="<?= e($link('/termini')) ?>"><?= e(__('platform.footer.terms')) ?></a></li>
        <li><a href="<?= e($link('/aiuto')) ?>"><?= e(__('platform.footer.help')) ?></a></li>
        <li><a href="<?= e((string) Config::get('site.source_url', 'https://noblogs.dev')) ?>" rel="noopener"><?= e(__('platform.footer.source')) ?></a></li>
      </ul>
    </nav>
    <p class="pf-footer-note"><?= e(__('platform.footer.no_tracking')) ?></p>
  </div>
</footer>

</body>
</html>
