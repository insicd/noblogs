<?php
/**
 * Impostazioni generali del blog.
 *
 * @var \Noblogs\Core\View   $this
 * @var \Noblogs\Models\Blog $blog
 * @var list<string>         $locales
 * @var array<string,string> $formats
 * @var array<string,mixed>  $old
 */

use Noblogs\Core\Csrf;
use Noblogs\Core\Url;

$base = '/dashboard/' . $blog->subdomain;
$hasOld = $old !== [];
$field = static fn(string $key, ?string $fallback): string => $hasOld
    ? (string) ($old[$key] ?? '')
    : (string) ($fallback ?? '');
$flag = static fn(string $key, bool $fallback): bool => $hasOld ? isset($old[$key]) : $fallback;

$this->layout('layouts/dashboard');
?>
<header class="dash-head">
  <h1><?= e(__('settings.general_title')) ?></h1>
  <p class="dash-head-note">
    <?= e(__('settings.address')) ?>
    <a href="<?= e(Url::blogRoot($blog)) ?>" rel="noopener"><?= e(Url::blogRoot($blog)) ?></a>
  </p>
</header>

<form method="post" action="<?= e(Url::to($base . '/impostazioni')) ?>" class="panel form">
  <?= Csrf::field() ?>

  <div class="field">
    <label for="title"><?= e(__('settings.title_field')) ?></label>
    <input type="text" id="title" name="title" maxlength="200" required value="<?= e($field('title', $blog->title)) ?>">
  </div>

  <div class="field">
    <label for="meta_description"><?= e(__('settings.description')) ?></label>
    <textarea id="meta_description" name="meta_description" rows="2" maxlength="300"><?= e($field('meta_description', $blog->meta_description)) ?></textarea>
    <p class="hint"><?= e(__('settings.description_hint')) ?></p>
  </div>

  <div class="field">
    <label for="meta_image"><?= e(__('settings.meta_image')) ?></label>
    <input type="text" id="meta_image" name="meta_image" maxlength="300" value="<?= e($field('meta_image', $blog->meta_image)) ?>">
    <p class="hint"><?= e(__('settings.meta_image_hint')) ?></p>
  </div>

  <div class="field field-narrow">
    <label for="favicon"><?= e(__('settings.favicon')) ?></label>
    <input type="text" id="favicon" name="favicon" maxlength="300" value="<?= e($field('favicon', $blog->favicon)) ?>">
    <p class="hint"><?= e(__('settings.favicon_hint')) ?></p>
  </div>

  <div class="field field-narrow">
    <label for="lang"><?= e(__('settings.lang')) ?></label>
    <input type="text" id="lang" name="lang" maxlength="10" required
           list="known-locales" value="<?= e($field('lang', $blog->lang)) ?>">
    <datalist id="known-locales">
      <?php foreach ($locales as $locale): ?>
        <option value="<?= e($locale) ?>"></option>
      <?php endforeach; ?>
    </datalist>
    <p class="hint"><?= e(__('settings.lang_hint')) ?></p>
  </div>

  <div class="field field-narrow">
    <label for="date_format"><?= e(__('settings.date_format')) ?></label>
    <input type="text" id="date_format" name="date_format" maxlength="32"
           list="date-formats" value="<?= e($field('date_format', $blog->date_format)) ?>">
    <datalist id="date-formats">
      <?php foreach ($formats as $format => $example): ?>
        <option value="<?= e($format) ?>"><?= e($example) ?></option>
      <?php endforeach; ?>
    </datalist>
    <p class="hint">
      <?= e(__('settings.date_format_hint')) ?>
      <?php foreach ($formats as $format => $example): ?>
        <code><?= e($format) ?></code> → <?= e($example) ?><br>
      <?php endforeach; ?>
    </p>
  </div>

  <div class="field field-narrow">
    <label for="blog_path"><?= e(__('settings.blog_path')) ?></label>
    <div class="input-prefix">
      <span class="prefix"><?= e(rtrim(Url::blogRoot($blog), '/')) ?>/</span>
      <input type="text" id="blog_path" name="blog_path" maxlength="100"
             autocapitalize="none" autocomplete="off" spellcheck="false"
             value="<?= e($field('blog_path', $blog->blog_path)) ?>">
    </div>
    <p class="hint"><?= e(__('settings.blog_path_hint')) ?></p>
  </div>

  <ul class="checkbox-list">
    <li>
      <label>
        <input type="checkbox" name="analytics_active" value="1" <?= $flag('analytics_active', $blog->analytics_active) ? 'checked' : '' ?>>
        <?= e(__('settings.analytics')) ?>
      </label>
      <p class="hint"><?= e(__('settings.analytics_hint')) ?></p>
    </li>
    <li>
      <label>
        <input type="checkbox" name="upvotes_active" value="1" <?= $flag('upvotes_active', $blog->upvotes_active) ? 'checked' : '' ?>>
        <?= e(__('settings.upvotes')) ?>
      </label>
      <p class="hint"><?= e(__('settings.upvotes_hint')) ?></p>
    </li>
    <li>
      <label>
        <input type="checkbox" name="subscriptions_active" value="1" <?= $flag('subscriptions_active', $blog->subscriptions_active) ? 'checked' : '' ?>>
        <?= e(__('settings.subscriptions')) ?>
      </label>
      <p class="hint"><?= e(__('settings.subscriptions_hint')) ?></p>
    </li>
    <li>
      <label>
        <input type="checkbox" name="discoverable" value="1" <?= $flag('discoverable', $blog->discoverable) ? 'checked' : '' ?>>
        <?= e(__('settings.discoverable')) ?>
      </label>
      <p class="hint"><?= e(__('settings.discoverable_hint')) ?></p>
    </li>
  </ul>

  <div class="actions">
    <button type="submit" class="primary"><?= e(__('form.save')) ?></button>
  </div>
</form>

<section class="panel">
  <h2><?= e(__('settings.more_title')) ?></h2>
  <ul class="link-grid">
    <li><a href="<?= e(Url::to($base . '/impostazioni/avanzate')) ?>"><?= e(__('settings.advanced_title')) ?></a></li>
    <li><a href="<?= e(Url::to($base . '/impostazioni/dominio')) ?>"><?= e(__('settings.domain_title')) ?></a></li>
    <li><a href="<?= e(Url::to($base . '/impostazioni/redirect')) ?>"><?= e(__('settings.redirects_title')) ?></a></li>
  </ul>
</section>

<section class="panel danger-panel">
  <h2><?= e(__('settings.delete_title')) ?></h2>
  <p><?= e(__('settings.delete_intro')) ?></p>
  <details class="danger-details">
    <summary><?= e(__('settings.delete_summary')) ?></summary>
    <form method="post" action="<?= e(Url::to($base . '/elimina')) ?>">
      <?= Csrf::field() ?>
      <div class="field">
        <label for="confirm"><?= e(__('settings.delete_confirm_label', ['blog' => $blog->subdomain])) ?></label>
        <input type="text" id="confirm" name="confirm" required
               autocapitalize="none" autocomplete="off" spellcheck="false">
      </div>
      <button type="submit" class="danger"><?= e(__('settings.delete_button')) ?></button>
    </form>
  </details>
</section>
