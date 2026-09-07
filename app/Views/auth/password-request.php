<?php
/**
 * Richiesta del link per reimpostare la password.
 *
 * @var \Noblogs\Core\View $this
 * @var array<string,mixed> $old
 */

use Noblogs\Core\Csrf;
use Noblogs\Core\Url;

$this->layout('layouts/platform');
$this->start('content');
?>
<div class="pf-shell pf-narrow">
  <h1><?= e(__('auth.password.request_heading')) ?></h1>
  <p class="pf-lead"><?= e(__('auth.password.request_intro')) ?></p>

  <form class="pf-form" method="post" action="<?= e(Url::to('/password/dimenticata')) ?>">
    <?= Csrf::field() ?>

    <div class="pf-field">
      <label for="nb-email"><?= e(__('auth.password.email')) ?></label>
      <input type="email" id="nb-email" name="email" required autocomplete="email"
             autocapitalize="none" spellcheck="false" maxlength="191"
             value="<?= e((string) ($old['email'] ?? '')) ?>">
    </div>

    <button type="submit" class="pf-button pf-button-big"><?= e(__('auth.password.request_submit')) ?></button>
  </form>

  <p class="pf-form-links">
    <a href="<?= e(Url::to('/accedi')) ?>"><?= e(__('auth.password.back_to_login')) ?></a>
  </p>
</div>
<?php
$this->end();
echo $this->slot('content'); // vedi la nota sullo slot in layouts/platform.php
