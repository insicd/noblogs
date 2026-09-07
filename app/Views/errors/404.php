<?php
/**
 * Pagina non trovata sulla piattaforma.
 *
 * Viene usata anche da Controller::notFound(), quindi il messaggio può
 * arrivare vuoto.
 *
 * @var \Noblogs\Core\View $this
 * @var string|null $message
 */

use Noblogs\Core\Url;

$message = (string) ($message ?? '');

$this->layout('layouts/platform', [
    'pageTitle' => __('errors.404.title'),
    'bodyClass' => 'error error-404',
    'indexable' => false,
]);
$this->start('content');
?>
<div class="pf-shell pf-narrow pf-error">
  <p class="pf-error-code" aria-hidden="true">404</p>
  <h1><?= e(__('errors.404.title')) ?></h1>
  <p class="pf-lead"><?= e($message !== '' ? $message : __('errors.404.body')) ?></p>

  <p><?= e(__('errors.404.links')) ?></p>
  <ul class="pf-error-links">
    <li><a href="<?= e(Url::platform('/')) ?>"><?= e(__('errors.home')) ?></a></li>
    <li><a href="<?= e(Url::platform('/esplora')) ?>"><?= e(__('discover.heading')) ?></a></li>
    <li><a href="<?= e(Url::platform('/aiuto')) ?>"><?= e(__('platform.nav.help')) ?></a></li>
  </ul>
</div>
<?php
$this->end();
echo $this->slot('content'); // vedi la nota sullo slot in layouts/platform.php
