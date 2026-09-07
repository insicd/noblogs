<?php
/**
 * Accesso.
 *
 * @var \Noblogs\Core\View $this
 * @var array<string,mixed> $old
 * @var string $next
 */

use Noblogs\Core\Csrf;
use Noblogs\Core\Url;

$action = Url::to('/accedi') . ($next !== '' ? '?next=' . rawurlencode($next) : '');

$this->layout('layouts/platform');
$this->start('content');
?>
<div class="pf-shell pf-narrow">
  <h1><?= e(__('auth.login.heading')) ?></h1>
  <p class="pf-lead"><?= e($next !== '' ? __('auth.login.next_notice') : __('auth.login.intro')) ?></p>

  <form class="pf-form" method="post" action="<?= e($action) ?>">
    <?= Csrf::field() ?>
    <?php if ($next !== ''): ?>
      <input type="hidden" name="next" value="<?= e($next) ?>">
    <?php endif; ?>

    <div class="pf-field">
      <label for="nb-email"><?= e(__('auth.login.email')) ?></label>
      <input type="email" id="nb-email" name="email" required autocomplete="username"
             autocapitalize="none" spellcheck="false" maxlength="191"
             value="<?= e((string) ($old['email'] ?? '')) ?>">
    </div>

    <div class="pf-field">
      <label for="nb-password"><?= e(__('auth.login.password')) ?></label>
      <input type="password" id="nb-password" name="password" required
             autocomplete="current-password">
    </div>

    <button type="submit" class="pf-button pf-button-big"><?= e(__('auth.login.submit')) ?></button>
  </form>

  <p class="pf-form-links">
    <a href="<?= e(Url::to('/password/dimenticata')) ?>"><?= e(__('auth.login.forgot')) ?></a>
  </p>
  <p class="pf-form-links">
    <?= e(__('auth.login.no_account')) ?>
    <a href="<?= e(Url::to('/registrati')) ?>"><?= e(__('auth.login.register')) ?></a>
  </p>
</div>
<?php
$this->end();
echo $this->slot('content'); // vedi la nota sullo slot in layouts/platform.php
