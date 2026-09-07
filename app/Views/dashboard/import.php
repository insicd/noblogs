<?php
/**
 * Importazione di file markdown, in due passaggi: prima si vede cosa
 * succederebbe, poi si conferma.
 *
 * @var \Noblogs\Core\View   $this
 * @var \Noblogs\Models\Blog $blog
 * @var list<array<string,mixed>> $items
 * @var bool                 $zip
 * @var int                  $maxFiles
 */

use Noblogs\Core\Csrf;
use Noblogs\Core\Url;

$base = '/dashboard/' . $blog->subdomain;
$errors = $errors ?? [];
$skipped = $skipped ?? 0;
$conflicts = 0;
foreach ($items as $item) {
    if (!empty($item['conflict'])) {
        $conflicts++;
    }
}

$this->layout('layouts/dashboard');
?>
<header class="dash-head">
  <h1><?= e(__('import.title')) ?></h1>
  <p class="dash-head-note"><?= e(__('import.intro')) ?></p>
</header>

<?php if ($errors !== []): ?>
  <ul class="dash-flash">
    <?php foreach ($errors as $error): ?>
      <li class="error"><?= e($error) ?></li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<?php if ($items === []): ?>
  <form method="post" action="<?= e(Url::to($base . '/importa')) ?>" enctype="multipart/form-data" class="panel form">
    <?= Csrf::field() ?>
    <input type="hidden" name="azione" value="anteprima">

    <div class="field">
      <label for="file"><?= e(__('import.field')) ?></label>
      <input type="file" id="file" name="file[]" multiple required
             accept="<?= e($zip ? '.md,.markdown,.txt,.zip' : '.md,.markdown,.txt') ?>">
      <p class="hint">
        <?= e($zip ? __('import.field_hint_zip') : __('import.field_hint_md')) ?>
        <?= e(__('import.field_hint_max', ['max' => $maxFiles])) ?>
      </p>
    </div>

    <div class="actions">
      <button type="submit" class="primary"><?= e(__('import.read_button')) ?></button>
    </div>
  </form>

  <section class="notice">
    <h2><?= e(__('import.format_title')) ?></h2>
    <p><?= e(__('import.format_body')) ?></p>
    <pre class="sample">---
titolo: Il mio articolo
data: 2026-03-05 09:30:00
tag: appunti, lavoro
pubblicato: sì
---

Testo dell'articolo in markdown.</pre>
    <p class="hint"><?= e(__('import.format_hint')) ?></p>
  </section>

  <section class="panel">
    <h2><?= e(__('export.title')) ?></h2>
    <p><?= e(__('export.intro')) ?></p>
    <p>
      <a class="button" href="<?= e(Url::to($base . '/esporta')) ?>"><?= e(__('export.button_archive')) ?></a>
      <a class="button" href="<?= e(Url::to($base . '/esporta') . '?formato=csv') ?>"><?= e(__('export.button_csv')) ?></a>
    </p>
  </section>
<?php else: ?>
  <section class="panel">
    <h2><?= e(__('import.preview_title', ['count' => count($items)])) ?></h2>
    <?php if ($skipped > 0): ?>
      <p class="note"><?= e(__('import.preview_skipped', ['count' => $skipped, 'max' => $maxFiles])) ?></p>
    <?php endif; ?>

    <table class="data-table">
      <thead>
        <tr>
          <th scope="col"><?= e(__('import.column_file')) ?></th>
          <th scope="col"><?= e(__('post.column.title')) ?></th>
          <th scope="col"><?= e(__('post.form.slug')) ?></th>
          <th scope="col"><?= e(__('import.column_type')) ?></th>
          <th scope="col"><?= e(__('import.column_note')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $item): ?>
          <tr>
            <td class="mono"><?= e((string) $item['file']) ?></td>
            <td><?= e((string) $item['title']) ?></td>
            <td class="mono">/<?= e((string) $item['slug']) ?></td>
            <td><?= e($item['is_page'] ? __('import.type_page') : __('import.type_post')) ?></td>
            <td>
              <?php if (!empty($item['conflict'])): ?>
                <span class="pill draft"><?= e(__('import.conflict')) ?></span>
              <?php endif; ?>
              <?php if (empty($item['is_published'])): ?>
                <span class="pill draft"><?= e(__('post.status.draft')) ?></span>
              <?php endif; ?>
              <?php foreach ((array) ($item['warnings'] ?? []) as $warning): ?>
                <span class="warning-note"><?= e((string) $warning) ?></span>
              <?php endforeach; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <form method="post" action="<?= e(Url::to($base . '/importa')) ?>" class="panel form">
    <?= Csrf::field() ?>
    <input type="hidden" name="azione" value="conferma">

    <?php if ($conflicts > 0): ?>
      <fieldset>
        <legend><?= e(__('import.conflicts_title', ['count' => $conflicts])) ?></legend>
        <ul class="checkbox-list">
          <li>
            <label>
              <input type="radio" name="conflitti" value="rinomina" checked>
              <?= e(__('import.conflict_rename')) ?>
            </label>
          </li>
          <li>
            <label>
              <input type="radio" name="conflitti" value="salta">
              <?= e(__('import.conflict_skip')) ?>
            </label>
          </li>
          <li>
            <label>
              <input type="radio" name="conflitti" value="sovrascrivi">
              <?= e(__('import.conflict_overwrite')) ?>
            </label>
          </li>
        </ul>
      </fieldset>
    <?php else: ?>
      <input type="hidden" name="conflitti" value="rinomina">
    <?php endif; ?>

    <div class="actions">
      <button type="submit" class="primary"><?= e(__('import.confirm_button')) ?></button>
      <a href="<?= e(Url::to($base . '/importa')) ?>"><?= e(__('form.cancel')) ?></a>
    </div>
  </form>
<?php endif; ?>
