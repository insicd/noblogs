<?php
/**
 * Scelta della nuova password.
 *
 * @var \Noblogs\Core\View $this
 * @var string $state 'form' oppure 'invalid'
 * @var string $token
 */

use Noblogs\Controllers\Auth\RegisterController;
use Noblogs\Core\Csrf;
use Noblogs\Core\Url;

$this->layout('layouts/platform');
$this->start('content');
?>
<div class="pf-shell pf-narrow">
<?php if ($state === 'invalid'): ?>

  <h1><?= e(__('auth.password.invalid_title')) ?></h1>
  <p><?= e(__('auth.password.invalid_body')) ?></p>
  <p class="pf-form-links">
    <a href="<?= e(Url::to('/password/dimenticata')) ?>"><?= e(__('auth.password.request_again')) ?></a>
  </p>

<?php else: ?>

  <h1><?= e(__('auth.password.reset_heading')) ?></h1>
  <p class="pf-lead"><?= e(__('auth.password.reset_intro')) ?></p>

  <form class="pf-form" method="post" action="<?= e(Url::to('/password/reimposta')) ?>">
    <?= Csrf::field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">

    <div class="pf-field">
      <label for="nb-password"><?= e(__('auth.password.new')) ?></label>
      <input type="password" id="nb-password" name="password" required
             autocomplete="new-password" minlength="<?= e((string) RegisterController::MIN_PASSWORD) ?>"
             maxlength="<?= e((string) RegisterController::MAX_PASSWORD) ?>"
             aria-describedby="nb-password-hint" autofocus>
      <p class="pf-hint" id="nb-password-hint">
        <?= e(__('auth.register.password_hint', ['min' => RegisterController::MIN_PASSWORD])) ?>
      </p>
    </div>

    <button type="submit" class="pf-button pf-button-big"><?= e(__('auth.password.reset_submit')) ?></button>
  </form>

<?php endif; ?>
</div>
<?php
$this->end();
echo $this->slot('content'); // vedi la nota sullo slot in layouts/platform.php
