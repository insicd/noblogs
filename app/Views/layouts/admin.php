<?php
/**
 * Guscio dell'area di amministrazione.
 *
 * Volutamente diverso dalla dashboard dell'utente: barra scura, etichetta del
 * ruolo sempre visibile, accento rosso sulle azioni distruttive. Chi entra qui
 * agisce sui contenuti di altre persone e deve accorgersene senza doverlo
 * dedurre dall'indirizzo nella barra del browser.
 *
 * @var \Noblogs\Core\View        $this
 * @var \Noblogs\Models\User      $staff
 * @var list<array{type:string,message:string}> $flashes
 * @var int         $pendingCount
 * @var string      $activeNav
 * @var string|null $notice
 */

use Noblogs\Core\Csrf;
use Noblogs\Core\Url;

/** @var string $siteName */
$siteName = $siteName ?? 'Noblogs';

$nav = [
    ['key' => 'dashboard', 'href' => '/admin',               'label' => __('admin.nav.dashboard'), 'badge' => $pendingCount],
    ['key' => 'blogs',     'href' => '/admin/blog',          'label' => __('admin.nav.blogs'),     'badge' => 0],
    ['key' => 'users',     'href' => '/admin/utenti',        'label' => __('admin.nav.users'),     'badge' => 0],
    ['key' => 'log',       'href' => '/admin/registro',      'label' => __('admin.nav.log'),       'badge' => 0],
];
if ($staff->isAdmin()) {
    $nav[] = ['key' => 'settings', 'href' => '/admin/impostazioni', 'label' => __('admin.nav.settings'), 'badge' => 0];
}
?>
<!doctype html>
<html lang="<?= e(\Noblogs\Core\I18n::locale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($this->slot('title', __('admin.area'))) ?> — <?= e($siteName) ?></title>
<link rel="stylesheet" href="<?= e(Url::asset('css/admin.css')) ?>">
</head>
<body class="admin">

<header class="admin-bar">
  <div class="admin-bar__inner">
    <a class="admin-bar__brand" href="<?= e(Url::to('/admin')) ?>">
      <span class="admin-bar__mark"><?= e(__('admin.area_short')) ?></span>
      <span class="admin-bar__site"><?= e($siteName) ?></span>
    </a>

    <nav class="admin-bar__nav" aria-label="<?= e(__('admin.nav.label')) ?>">
      <?php foreach ($nav as $item): ?>
        <a href="<?= e(Url::to($item['href'])) ?>"<?= $activeNav === $item['key'] ? ' class="is-active" aria-current="page"' : '' ?>>
          <?= e($item['label']) ?><?php if ($item['badge'] > 0): ?><span class="admin-badge"><?= e((string) $item['badge']) ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="admin-bar__account">
      <span class="admin-role admin-role--<?= e($staff->role) ?>">
        <?= e($staff->isAdmin() ? __('admin.role.admin') : __('admin.role.moderator')) ?>
      </span>
      <span class="admin-bar__email"><?= e($staff->email) ?></span>
      <a href="<?= e(Url::to('/dashboard')) ?>"><?= e(__('admin.nav.back_to_dashboard')) ?></a>
      <form method="post" action="<?= e(Url::to('/esci')) ?>" class="admin-inline-form">
        <?= Csrf::field() ?>
        <button type="submit" class="admin-linkish"><?= e(__('admin.nav.logout')) ?></button>
      </form>
    </div>
  </div>
</header>

<main class="admin-main">

  <?php foreach ($flashes as $flash): ?>
    <p class="admin-flash admin-flash--<?= e($flash['type']) ?>"><?= e($flash['message']) ?></p>
  <?php endforeach; ?>

  <?php if ($notice !== null && trim($notice) !== ''): ?>
    <div class="admin-notice">
      <strong><?= e(__('admin.notice.title')) ?></strong>
      <p><?= nl2br(e($notice), false) ?></p>
    </div>
  <?php endif; ?>

  <div class="admin-head">
    <h1><?= e($this->slot('title', __('admin.area'))) ?></h1>
    <?php if ($this->hasSlot('intro')): ?>
      <p class="admin-intro"><?= e($this->slot('intro')) ?></p>
    <?php endif; ?>
  </div>

  <?= $this->slot('content') ?>
</main>

<footer class="admin-foot">
  <p><?= e(__('admin.footer', ['version' => NOBLOGS_VERSION])) ?></p>
</footer>

</body>
</html>
