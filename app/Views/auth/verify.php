<?php
/**
 * Esito della registrazione e verifica dell'indirizzo email.
 *
 * @var \Noblogs\Core\View $this
 * @var string $state 'sent' oppure 'invalid'
 * @var string $email
 */

use Noblogs\Core\Csrf;
use Noblogs\Core\Url;

$this->layout('layouts/platform');
$this->start('content');
?>
<div class="pf-shell pf-narrow">
<?php if ($state === 'invalid'): ?>

  <h1><?= e(__('auth.verify.invalid_title')) ?></h1>
  <p><?= e(__('auth.verify.invalid_body')) ?></p>
  <p class="pf-form-links">
    <a href="<?= e(Url::to('/verifica-email')) ?>"><?= e(__('auth.verify.title')) ?></a>
    <span aria-hidden="true">·</span>
    <a href="<?= e(Url::to('/accedi')) ?>"><?= e(__('auth.password.back_to_login')) ?></a>
  </p>

<?php else: ?>

  <h1><?= e(__('auth.verify.heading')) ?></h1>
  <p class="pf-lead"><?= e(__('auth.verify.body')) ?></p>

  <?php if ($email !== ''): ?>
    <p class="pf-notice"><?= e(__('auth.verify.sent_to', ['email' => $email])) ?></p>
  <?php endif; ?>

  <form class="pf-form" method="post" action="<?= e(Url::to('/verifica-email/rinvia')) ?>">
    <?= Csrf::field() ?>
    <?php if ($email === ''): ?>
      <div class="pf-field">
        <label for="nb-email"><?= e(__('auth.login.email')) ?></label>
        <input type="email" id="nb-email" name="email" required autocomplete="email"
               autocapitalize="none" spellcheck="false" maxlength="191">
      </div>
    <?php endif; ?>
    <button type="submit" class="pf-button"><?= e(__('auth.verify.resend')) ?></button>
  </form>

  <p class="pf-form-links">
    <a href="<?= e(Url::to('/accedi')) ?>"><?= e(__('auth.password.back_to_login')) ?></a>
  </p>

<?php endif; ?>
</div>
<?php
$this->end();
echo $this->slot('content'); // vedi la nota sullo slot in layouts/platform.php
