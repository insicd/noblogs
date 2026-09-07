<?php
/**
 * Galleria dei temi e CSS personale.
 *
 * Ogni anteprima sta in un iframe con srcdoc: è l'unico modo di mostrare un
 * tema con i suoi stili veri senza che quegli stili invadano il pannello.
 * L'iframe è in sandbox e senza script: dentro c'è solo CSS.
 *
 * @var \Noblogs\Core\View   $this
 * @var \Noblogs\Models\Blog $blog
 * @var array<string,\Noblogs\Models\Theme> $themes
 * @var string               $sample
 * @var array<string,mixed>  $old
 */

use Noblogs\Core\Csrf;
use Noblogs\Core\Url;
use Noblogs\Models\Theme;

$base = '/dashboard/' . $blog->subdomain;
$hasOld = $old !== [];
$selected = $hasOld ? (string) ($old['theme'] ?? $blog->theme) : $blog->theme;
$css = $hasOld ? (string) ($old['custom_css'] ?? '') : (string) ($blog->custom_css ?? '');
$overwrite = $hasOld ? isset($old['overwrite_styles']) : $blog->overwrite_styles;

$document = static fn(string $stylesheet, string $body): string =>
    '<!doctype html><html><head><meta charset="utf-8">'
    . '<meta name="viewport" content="width=device-width, initial-scale=1">'
    . '<style>' . $stylesheet . '</style></head><body>' . $body . '</body></html>';

$this->layout('layouts/dashboard');
?>
<header class="dash-head">
  <h1><?= e(__('theme.title')) ?></h1>
  <p class="dash-head-note"><?= e(__('theme.intro')) ?></p>
</header>

<form method="post" action="<?= e(Url::to($base . '/aspetto')) ?>" class="panel form">
  <?= Csrf::field() ?>

  <fieldset class="theme-gallery">
    <legend><?= e(__('theme.gallery')) ?></legend>

    <?php foreach ($themes as $slug => $theme): ?>
      <label class="theme-card <?= $selected === $slug ? 'selected' : '' ?>">
        <input type="radio" name="theme" value="<?= e($slug) ?>" <?= $selected === $slug ? 'checked' : '' ?>>
        <span class="theme-name"><?= e($theme->title) ?></span>
        <iframe class="theme-preview" title="<?= e(__('theme.preview_of', ['name' => $theme->title])) ?>"
                sandbox="" loading="lazy" tabindex="-1" scrolling="no"
                srcdoc="<?= e($document(Theme::sanitize($theme->css ?? ''), $sample)) ?>"></iframe>
        <?php if ($theme->description !== null && $theme->description !== ''): ?>
          <span class="theme-description"><?= e($theme->description) ?></span>
        <?php endif; ?>
      </label>
    <?php endforeach; ?>
  </fieldset>

  <div class="field">
    <label for="custom_css"><?= e(__('theme.custom_css')) ?></label>
    <textarea id="custom_css" name="custom_css" rows="14" class="mono"
              spellcheck="false"><?= e($css) ?></textarea>
    <p class="hint"><?= e(__('theme.custom_css_hint')) ?></p>
  </div>

  <ul class="checkbox-list">
    <li>
      <label>
        <input type="checkbox" name="overwrite_styles" value="1" <?= $overwrite ? 'checked' : '' ?>>
        <?= e(__('theme.overwrite')) ?>
      </label>
      <p class="hint"><?= e(__('theme.overwrite_hint')) ?></p>
    </li>
  </ul>

  <div class="actions">
    <button type="submit" class="primary"><?= e(__('form.save')) ?></button>
  </div>
</form>

<section class="panel">
  <h2><?= e(__('theme.current_title')) ?></h2>
  <p class="note"><?= e(__('theme.current_intro')) ?></p>
  <iframe class="theme-preview large" title="<?= e(__('theme.current_title')) ?>"
          sandbox="" loading="lazy"
          srcdoc="<?= e($document(Theme::stylesheetFor($blog), $sample)) ?>"></iframe>
  <p><a href="<?= e(Url::blogRoot($blog)) ?>" rel="noopener"><?= e(__('dashboard.visit')) ?> ↗</a></p>
</section>
