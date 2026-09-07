<?php
/**
 * Impostazioni avanzate: robots.txt, alias del feed, modello dei post e
 * codice nell'intestazione e nel piè di pagina.
 *
 * @var \Noblogs\Core\View   $this
 * @var \Noblogs\Models\Blog $blog
 * @var string               $contactEmail
 * @var array<string,mixed>  $old
 */

use Noblogs\Core\Csrf;
use Noblogs\Core\Url;

$base = '/dashboard/' . $blog->subdomain;
$hasOld = $old !== [];
$field = static fn(string $key, ?string $fallback): string => $hasOld
    ? (string) ($old[$key] ?? '')
    : (string) ($fallback ?? '');

$this->layout('layouts/dashboard');
?>
<header class="dash-head">
  <h1><?= e(__('settings.advanced_title')) ?></h1>
  <p class="dash-head-note"><?= e(__('settings.advanced_intro')) ?></p>
</header>

<form method="post" action="<?= e(Url::to($base . '/impostazioni/avanzate')) ?>" class="panel form">
  <?= Csrf::field() ?>

  <div class="field">
    <label for="robots_txt">robots.txt</label>
    <textarea id="robots_txt" name="robots_txt" rows="6" class="mono"
              spellcheck="false"><?= e($field('robots_txt', $blog->robots_txt)) ?></textarea>
    <p class="hint"><?= e(__('settings.robots_hint')) ?></p>
  </div>

  <div class="field field-narrow">
    <label for="rss_alias"><?= e(__('settings.rss_alias')) ?></label>
    <div class="input-prefix">
      <span class="prefix"><?= e(rtrim(Url::blogRoot($blog), '/')) ?>/</span>
      <input type="text" id="rss_alias" name="rss_alias" maxlength="100"
             autocapitalize="none" autocomplete="off" spellcheck="false"
             value="<?= e($field('rss_alias', $blog->rss_alias)) ?>">
    </div>
    <p class="hint"><?= e(__('settings.rss_alias_hint')) ?></p>
  </div>

  <div class="field">
    <label for="post_template"><?= e(__('settings.post_template')) ?></label>
    <textarea id="post_template" name="post_template" rows="8" class="mono"><?= e($field('post_template', $blog->post_template)) ?></textarea>
    <p class="hint"><?= e(__('settings.post_template_hint')) ?></p>
  </div>

  <?php if ($blog->allow_raw_html): ?>
    <div class="field">
      <label for="header_directive"><?= e(__('settings.header_code')) ?></label>
      <textarea id="header_directive" name="header_directive" rows="6" class="mono"
                spellcheck="false"><?= e($field('header_directive', $blog->header_directive)) ?></textarea>
      <p class="hint"><?= e(__('settings.header_code_hint')) ?></p>
    </div>

    <div class="field">
      <label for="footer_directive"><?= e(__('settings.footer_code')) ?></label>
      <textarea id="footer_directive" name="footer_directive" rows="6" class="mono"
                spellcheck="false"><?= e($field('footer_directive', $blog->footer_directive)) ?></textarea>
      <p class="hint"><?= e(__('settings.footer_code_hint')) ?></p>
    </div>
  <?php else: ?>
    <div class="notice">
      <h3><?= e(__('settings.raw_html_locked_title')) ?></h3>
      <p><?= e(__('settings.raw_html_locked_body')) ?></p>
      <?php if ($contactEmail !== ''): ?>
        <p>
          <?= e(__('settings.raw_html_locked_ask')) ?>
          <a href="mailto:<?= e($contactEmail) ?>"><?= e($contactEmail) ?></a>
        </p>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="actions">
    <button type="submit" class="primary"><?= e(__('form.save')) ?></button>
  </div>
</form>
