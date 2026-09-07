<?php
/**
 * Accesso non consentito.
 *
 * @var \Noblogs\Core\View $this
 * @var string|null $message
 */

use Noblogs\Core\Url;

$message = (string) ($message ?? '');

$this->layout('layouts/platform', [
    'pageTitle' => __('errors.403.title'),
    'bodyClass' => 'error error-403',
    'indexable' => false,
]);
$this->start('content');
?>
<div class="pf-shell pf-narrow pf-error">
  <p class="pf-error-code" aria-hidden="true">403</p>
  <h1><?= e(__('errors.403.title')) ?></h1>
  <p class="pf-lead"><?= e($message !== '' ? $message : __('errors.403.body')) ?></p>

  <ul class="pf-error-links">
    <li><a href="<?= e(Url::platform('/dashboard')) ?>"><?= e(__('platform.nav.dashboard')) ?></a></li>
    <li><a href="<?= e(Url::platform('/accedi')) ?>"><?= e(__('platform.nav.login')) ?></a></li>
    <li><a href="<?= e(Url::platform('/')) ?>"><?= e(__('errors.home')) ?></a></li>
  </ul>
</div>
<?php
$this->end();
echo $this->slot('content'); // vedi la nota sullo slot in layouts/platform.php
