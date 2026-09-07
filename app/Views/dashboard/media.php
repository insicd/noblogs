<?php
/**
 * File caricati: caricamento, spazio usato ed elenco.
 *
 * @var \Noblogs\Core\View   $this
 * @var \Noblogs\Models\Blog $blog
 * @var \Noblogs\Models\User $user
 * @var list<\Noblogs\Models\Media> $files
 * @var string               $usedHuman
 * @var string               $quotaHuman
 * @var int                  $percent
 * @var int                  $count
 * @var int                  $maxFiles
 * @var string               $maxBytes
 */

use Noblogs\Core\Csrf;
use Noblogs\Core\Url;
use Noblogs\Support\Dates;

$base = '/dashboard/' . $blog->subdomain;

$this->layout('layouts/dashboard');
?>
<header class="dash-head">
  <h1><?= e(__('media.title')) ?></h1>
  <p class="dash-head-note"><?= e(__('media.intro')) ?></p>
</header>

<section class="panel">
  <h2><?= e(__('media.quota_title')) ?></h2>
  <p class="quota-line">
    <?= e(__('media.quota_used', ['used' => $usedHuman, 'quota' => $quotaHuman])) ?>
    · <?= e(__('media.file_count', ['count' => $count, 'max' => $maxFiles])) ?>
  </p>
  <span class="bar-track wide">
    <span class="bar-fill" style="width: <?= e((string) $percent) ?>%"></span>
  </span>
</section>

<form method="post" action="<?= e(Url::to($base . '/file')) ?>" enctype="multipart/form-data" class="panel form">
  <?= Csrf::field() ?>

  <div class="field">
    <label for="file"><?= e(__('media.upload_label')) ?></label>
    <input type="file" id="file" name="file[]" multiple required>
    <p class="hint"><?= e(__('media.upload_hint', ['size' => $maxBytes])) ?></p>
  </div>

  <div class="actions">
    <button type="submit" class="primary"><?= e(__('media.upload_button')) ?></button>
  </div>
</form>

<?php if ($files === []): ?>
  <div class="dash-empty">
    <p><?= e(__('media.empty')) ?></p>
  </div>
<?php else: ?>
  <ul class="media-grid">
    <?php foreach ($files as $media): ?>
      <?php
        $url = Url::media($blog, $media->path);
        $markdown = $media->isImage()
            ? '![' . $media->filename . '](' . $url . ')'
            : '[' . $media->filename . '](' . $url . ')';
        $fieldId = 'media-md-' . $media->id;
        $created = Dates::parse($media->created_at);
      ?>
      <li class="media-item">
        <?php if ($media->isImage()): ?>
          <a href="<?= e($url) ?>" rel="noopener" class="media-thumb">
            <img src="<?= e($url) ?>" alt="<?= e($media->filename) ?>" loading="lazy">
          </a>
        <?php else: ?>
          <a href="<?= e($url) ?>" rel="noopener" class="media-thumb file">
            <span class="media-ext"><?= e(mb_strtoupper(pathinfo($media->filename, PATHINFO_EXTENSION))) ?></span>
          </a>
        <?php endif; ?>

        <p class="media-name"><a href="<?= e($url) ?>" rel="noopener"><?= e($media->filename) ?></a></p>
        <p class="media-meta">
          <?= e($media->humanSize()) ?>
          <?php if ($media->width !== null && $media->height !== null): ?>
            · <?= e($media->width . '×' . $media->height) ?>
          <?php endif; ?>
          <?php if ($created !== null): ?>
            · <time datetime="<?= e($created->format('c')) ?>"><?= e(Dates::format(Dates::toLocal($created, $user->timezone), 'j M Y', $user->locale)) ?></time>
          <?php endif; ?>
        </p>

        <p class="copy-row">
          <label class="visually-hidden" for="<?= e($fieldId) ?>"><?= e(__('media.markdown_label')) ?></label>
          <input type="text" id="<?= e($fieldId) ?>" readonly value="<?= e($markdown) ?>">
          <button type="button" class="button" data-copy="<?= e($fieldId) ?>" data-copied="<?= e(__('editor.copied')) ?>"><?= e(__('editor.copy')) ?></button>
        </p>

        <details class="danger-details">
          <summary><?= e(__('form.delete')) ?></summary>
          <form method="post" action="<?= e(Url::to($base . '/file/' . $media->id . '/elimina')) ?>">
            <?= Csrf::field() ?>
            <p><?= e(__('media.delete_confirm', ['name' => $media->filename])) ?></p>
            <button type="submit" class="danger"><?= e(__('form.delete')) ?></button>
          </form>
        </details>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
<?php
$this->start('scripts');
?>
<script src="<?= e(Url::asset('js/editor.js')) ?>" defer></script>
<?php
$this->end();
