<?php
/**
 * Impostazioni dell'account.
 *
 * @var \Noblogs\Core\View   $this
 * @var \Noblogs\Models\User $user
 * @var list<string>         $locales
 * @var list<string>         $timezones
 * @var list<\Noblogs\Models\Blog> $blogs
 * @var int                  $minLength
 * @var array<string,mixed>  $old
 */

use Noblogs\Core\Csrf;
use Noblogs\Core\Url;

$action = Url::to('/dashboard/account');

$this->layout('layouts/dashboard');
?>
<header class="dash-head">
  <h1><?= e(__('account.title')) ?></h1>
</header>

<section class="panel form">
  <h2><?= e(__('account.profile_title')) ?></h2>
  <form method="post" action="<?= e($action) ?>">
    <?= Csrf::field() ?>
    <input type="hidden" name="azione" value="profilo">

    <div class="field field-narrow">
      <label for="locale"><?= e(__('account.locale')) ?></label>
      <select id="locale" name="locale">
        <?php foreach ($locales as $locale): ?>
          <option value="<?= e($locale) ?>" <?= $user->locale === $locale ? 'selected' : '' ?>><?= e($locale) ?></option>
        <?php endforeach; ?>
      </select>
      <p class="hint"><?= e(__('account.locale_hint')) ?></p>
    </div>

    <div class="field field-narrow">
      <label for="timezone"><?= e(__('account.timezone')) ?></label>
      <select id="timezone" name="timezone">
        <?php foreach ($timezones as $zone): ?>
          <option value="<?= e($zone) ?>" <?= $user->timezone === $zone ? 'selected' : '' ?>><?= e($zone) ?></option>
        <?php endforeach; ?>
      </select>
      <p class="hint"><?= e(__('account.timezone_hint')) ?></p>
    </div>

    <div class="actions">
      <button type="submit" class="primary"><?= e(__('form.save')) ?></button>
    </div>
  </form>
</section>

<section class="panel form">
  <h2><?= e(__('account.email_title')) ?></h2>
  <p class="note"><?= e(__('account.email_intro')) ?></p>
  <?php if ($user->verify_token !== null): ?>
    <p class="note warning"><?= e(__('account.email_pending')) ?></p>
  <?php endif; ?>

  <form method="post" action="<?= e($action) ?>">
    <?= Csrf::field() ?>
    <input type="hidden" name="azione" value="email">

    <div class="field">
      <label for="email"><?= e(__('account.email')) ?></label>
      <input type="email" id="email" name="email" maxlength="191" required
             autocomplete="email" value="<?= e($old['email'] ?? $user->email) ?>">
    </div>

    <div class="field field-narrow">
      <label for="email_password"><?= e(__('account.current_password')) ?></label>
      <input type="password" id="email_password" name="current_password" required autocomplete="current-password">
      <p class="hint"><?= e(__('account.email_password_hint')) ?></p>
    </div>

    <div class="actions">
      <button type="submit" class="primary"><?= e(__('account.email_button')) ?></button>
    </div>
  </form>
</section>

<section class="panel form">
  <h2><?= e(__('account.password_title')) ?></h2>

  <form method="post" action="<?= e($action) ?>">
    <?= Csrf::field() ?>
    <input type="hidden" name="azione" value="password">

    <div class="field field-narrow">
      <label for="current_password"><?= e(__('account.current_password')) ?></label>
      <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
    </div>

    <div class="field field-narrow">
      <label for="password"><?= e(__('account.new_password')) ?></label>
      <input type="password" id="password" name="password" required
             minlength="<?= e((string) $minLength) ?>" autocomplete="new-password">
      <p class="hint"><?= e(__('account.password_hint', ['min' => $minLength])) ?></p>
    </div>

    <div class="field field-narrow">
      <label for="password_confirm"><?= e(__('account.confirm_password')) ?></label>
      <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password">
    </div>

    <div class="actions">
      <button type="submit" class="primary"><?= e(__('account.password_button')) ?></button>
    </div>
  </form>
</section>

<section class="panel danger-panel">
  <h2><?= e(__('account.delete_title')) ?></h2>
  <p><?= e(__('account.delete_intro')) ?></p>

  <?php if ($blogs !== []): ?>
    <p><?= e(__('account.delete_blogs')) ?></p>
    <ul>
      <?php foreach ($blogs as $blogItem): ?>
        <li><?= e($blogItem->title) ?> <span class="muted">(<?= e($blogItem->subdomain) ?>)</span></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <p class="note"><?= e(__('account.delete_export_hint')) ?></p>

  <details class="danger-details">
    <summary><?= e(__('account.delete_summary')) ?></summary>
    <form method="post" action="<?= e(Url::to('/dashboard/account/elimina')) ?>">
      <?= Csrf::field() ?>
      <div class="field field-narrow">
        <label for="delete_password"><?= e(__('account.delete_password_label')) ?></label>
        <input type="password" id="delete_password" name="password" required autocomplete="current-password">
      </div>
      <button type="submit" class="danger"><?= e(__('account.delete_button')) ?></button>
    </form>
  </details>
</section>
