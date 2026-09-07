<?php
/**
 * Guscio del pannello dell'utente.
 *
 * A differenza del layout dei blog, qui il foglio di stile è un file
 * collegato: le pagine del pannello sono molte, si visitano di seguito e
 * conviene che il CSS resti nella cache del browser.
 *
 * @var \Noblogs\Core\View      $this
 * @var \Noblogs\Models\User    $user
 * @var \Noblogs\Models\Blog|null $blog
 * @var string                  $section
 * @var string                  $pageTitle
 * @var string                  $siteName
 */

use Noblogs\Core\Csrf;
use Noblogs\Core\Session;
use Noblogs\Core\Url;
use Noblogs\Models\Theme;

$flashes = Session::takeFlash();
$blog = $blog ?? null;
$section = $section ?? '';
?>
<!doctype html>
<html lang="<?= e($user->locale) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> — <?= e($siteName) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="<?= e(Url::asset('css/dashboard.css')) ?>">
<?php if ($user->dashboard_css !== null && trim($user->dashboard_css) !== ''): ?>
<style><?= Theme::sanitize($user->dashboard_css) ?></style>
<?php endif; ?>
</head>
<body class="dash">

<header class="dash-top">
  <a class="dash-brand" href="<?= e(Url::to('/dashboard')) ?>"><?= e($siteName) ?></a>

  <nav class="dash-account" aria-label="<?= e(__('dashboard.account_menu')) ?>">
    <?php if ($user->isModerator()): ?>
      <a href="<?= e(Url::to('/admin')) ?>"><?= e(__('dashboard.admin_area')) ?></a>
    <?php endif; ?>
    <a href="<?= e(Url::to('/dashboard/account')) ?>" class="<?= $section === 'account' ? 'current' : '' ?>">
      <?= e($user->email) ?>
    </a>
    <form method="post" action="<?= e(Url::to('/esci')) ?>" class="dash-logout">
      <?= Csrf::field() ?>
      <button type="submit"><?= e(__('dashboard.logout')) ?></button>
    </form>
  </nav>
</header>

<?php if (!$user->hasVerifiedEmail()): ?>
<p class="dash-banner warning"><?= e(__('dashboard.email_not_verified')) ?></p>
<?php endif; ?>

<div class="dash-body<?= $blog === null ? ' no-sidebar' : '' ?>">

<?php if ($blog !== null): ?>
  <?php $this->partial('dashboard/partials/blog-nav', ['blog' => $blog, 'section' => $section]); ?>
<?php endif; ?>

  <main class="dash-main">
    <?php if ($flashes !== []): ?>
      <ul class="dash-flash" role="status">
        <?php foreach ($flashes as $flash): ?>
          <li class="<?= e($flash['type']) ?>"><?= e($flash['message']) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?= $this->slot('content') ?>
  </main>
</div>

<footer class="dash-foot">
  <a href="<?= e(Url::to('/aiuto/markdown')) ?>"><?= e(__('dashboard.markdown_help')) ?></a>
  <a href="<?= e(Url::to('/aiuto')) ?>"><?= e(__('dashboard.help')) ?></a>
  <a href="<?= e(Url::platform('/')) ?>"><?= e($siteName) ?></a>
</footer>

<?= $this->slot('scripts') ?>
</body>
</html>
