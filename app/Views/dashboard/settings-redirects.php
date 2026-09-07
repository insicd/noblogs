<?php
/**
 * Reindirizzamenti del blog, uno per riga.
 *
 * @var \Noblogs\Core\View   $this
 * @var \Noblogs\Models\Blog $blog
 * @var string               $text
 * @var array<string,mixed>  $old
 */

use Noblogs\Core\Csrf;
use Noblogs\Core\Url;

$base = '/dashboard/' . $blog->subdomain;
$value = $old !== [] ? (string) ($old['redirects'] ?? '') : $text;

$this->layout('layouts/dashboard');
?>
<header class="dash-head">
  <h1><?= e(__('settings.redirects_title')) ?></h1>
  <p class="dash-head-note"><?= e(__('settings.redirects_intro')) ?></p>
</header>

<form method="post" action="<?= e(Url::to($base . '/impostazioni/redirect')) ?>" class="panel form">
  <?= Csrf::field() ?>

  <div class="field">
    <label for="redirects"><?= e(__('settings.redirects_field')) ?></label>
    <textarea id="redirects" name="redirects" rows="12" class="mono"
              spellcheck="false"
              placeholder="/vecchio-articolo /nuovo-articolo 301"><?= e($value) ?></textarea>
  </div>

  <div class="notice">
    <h3><?= e(__('settings.redirects_syntax_title')) ?></h3>
    <ul>
      <li><?= e(__('settings.redirects_syntax_1')) ?></li>
      <li><?= e(__('settings.redirects_syntax_2')) ?></li>
      <li><?= e(__('settings.redirects_syntax_3')) ?></li>
      <li><?= e(__('settings.redirects_syntax_4')) ?></li>
    </ul>
    <pre class="sample">/vecchio /nuovo
/blog/2019/appunti /appunti 301
/vecchia-pagina https://altro-sito.it/pagina</pre>
  </div>

  <div class="actions">
    <button type="submit" class="primary"><?= e(__('form.save')) ?></button>
  </div>
</form>
