<?php
/**
 * Registrazione: account e primo blog in un unico modulo.
 *
 * @var \Noblogs\Core\View $this
 * @var array<string,mixed> $old
 * @var string $domain
 */

use Noblogs\Controllers\Auth\RegisterController;
use Noblogs\Core\Csrf;
use Noblogs\Core\Url;

$this->layout('layouts/platform');
$this->start('content');
?>
<div class="pf-shell pf-narrow">
  <h1><?= e(__('auth.register.heading')) ?></h1>
  <p class="pf-lead"><?= e(__('auth.register.intro')) ?></p>

  <form class="pf-form" method="post" action="<?= e(Url::to('/registrati')) ?>">
    <?= Csrf::field() ?>

    <div class="pf-field">
      <label for="nb-email"><?= e(__('auth.register.email')) ?></label>
      <input type="email" id="nb-email" name="email" required autocomplete="email"
             autocapitalize="none" spellcheck="false" maxlength="191"
             aria-describedby="nb-email-hint"
             value="<?= e((string) ($old['email'] ?? '')) ?>">
      <p class="pf-hint" id="nb-email-hint"><?= e(__('auth.register.email_hint')) ?></p>
    </div>

    <div class="pf-field">
      <label for="nb-password"><?= e(__('auth.register.password')) ?></label>
      <input type="password" id="nb-password" name="password" required
             autocomplete="new-password" minlength="<?= e((string) RegisterController::MIN_PASSWORD) ?>"
             maxlength="<?= e((string) RegisterController::MAX_PASSWORD) ?>"
             aria-describedby="nb-password-hint">
      <p class="pf-hint" id="nb-password-hint">
        <?= e(__('auth.register.password_hint', ['min' => RegisterController::MIN_PASSWORD])) ?>
      </p>
    </div>

    <div class="pf-field">
      <label for="nb-subdomain"><?= e(__('auth.register.subdomain')) ?></label>
      <div class="pf-field-inline">
        <input type="text" id="nb-subdomain" name="subdomain" required
               inputmode="url" autocapitalize="none" spellcheck="false"
               maxlength="63" pattern="[a-z0-9-]+" placeholder="ilmioblog"
               aria-describedby="nb-subdomain-hint"
               value="<?= e((string) ($old['subdomain'] ?? '')) ?>">
        <span class="pf-field-suffix">.<?= e($domain) ?></span>
      </div>
      <p class="pf-hint" id="nb-subdomain-hint"><?= e(__('auth.register.subdomain_hint')) ?></p>
    </div>

    <div class="pf-field">
      <label for="nb-title"><?= e(__('auth.register.blog_title')) ?></label>
      <input type="text" id="nb-title" name="title" required maxlength="200"
             aria-describedby="nb-title-hint"
             value="<?= e((string) ($old['title'] ?? '')) ?>">
      <p class="pf-hint" id="nb-title-hint"><?= e(__('auth.register.blog_title_hint')) ?></p>
    </div>

    <?php /* Campo esca: nascosto via CSS, i programmi lo compilano comunque. */ ?>
    <div class="nb-hp" aria-hidden="true">
      <label for="nb-website"><?= e(__('form.leave_empty')) ?></label>
      <input type="text" id="nb-website" name="website" tabindex="-1" autocomplete="off">
    </div>

    <button type="submit" class="pf-button pf-button-big"><?= e(__('auth.register.submit')) ?></button>

    <p class="pf-hint">
      <?= e(__('auth.register.terms_note')) ?>
      <a href="<?= e(Url::to('/termini')) ?>"><?= e(__('platform.footer.terms')) ?></a>
      <span aria-hidden="true">·</span>
      <a href="<?= e(Url::to('/privacy')) ?>"><?= e(__('platform.footer.privacy')) ?></a>
    </p>
  </form>

  <p class="pf-notice"><?= e(__('auth.register.review_notice')) ?></p>

  <p class="pf-form-links">
    <?= e(__('auth.register.have_account')) ?>
    <a href="<?= e(Url::to('/accedi')) ?>"><?= e(__('auth.register.login')) ?></a>
  </p>
</div>
<?php
$this->end();
echo $this->slot('content'); // vedi la nota sullo slot in layouts/platform.php
