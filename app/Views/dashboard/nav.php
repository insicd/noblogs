<?php
/**
 * Barra di navigazione del blog, scritta in markdown.
 *
 * @var \Noblogs\Core\View   $this
 * @var \Noblogs\Models\Blog $blog
 * @var string               $previewHtml Nav già reso da Renderer.
 * @var string               $suggestion
 * @var array<string,mixed>  $old
 */

use Noblogs\Core\Csrf;
use Noblogs\Core\Url;

$base = '/dashboard/' . $blog->subdomain;
$value = $old !== [] ? (string) ($old['nav'] ?? '') : (string) ($blog->nav ?? '');

$this->layout('layouts/dashboard');
?>
<header class="dash-head">
  <h1><?= e(__('nav.title')) ?></h1>
  <p class="dash-head-note"><?= e(__('nav.intro')) ?></p>
</header>

<form method="post" action="<?= e(Url::to($base . '/navigazione')) ?>" class="panel form">
  <?= Csrf::field() ?>

  <div class="field">
    <label for="nav"><?= e(__('nav.field')) ?></label>
    <textarea id="nav" name="nav" rows="6" class="mono"><?= e($value) ?></textarea>
    <p class="hint"><?= e(__('nav.field_hint')) ?></p>
  </div>

  <div class="actions">
    <button type="submit" class="primary"><?= e(__('form.save')) ?></button>
  </div>
</form>

<section class="panel">
  <h2><?= e(__('nav.preview_title')) ?></h2>
  <p class="note"><?= e(__('nav.preview_intro')) ?></p>
  <?php if (trim($previewHtml) === ''): ?>
    <p class="note"><?= e(__('nav.preview_empty')) ?></p>
  <?php else: ?>
    <nav class="nav-preview"><?= $previewHtml ?></nav>
  <?php endif; ?>
</section>

<section class="panel">
  <h2><?= e(__('nav.examples_title')) ?></h2>
  <pre class="sample"><?= e($suggestion) ?>
[<?= e(__('nav.example_about')) ?>](/chi-sono/) [<?= e(__('nav.example_tags')) ?>](/blog/?tag=appunti)
[RSS](/feed/)</pre>
  <p class="hint"><?= e(__('nav.examples_hint')) ?></p>
</section>
