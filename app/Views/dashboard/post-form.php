<?php
/**
 * Scrittura di un articolo o di una pagina.
 *
 * @var \Noblogs\Core\View   $this
 * @var \Noblogs\Models\Blog $blog
 * @var \Noblogs\Models\Post $post
 * @var \Noblogs\Models\User $user
 * @var string               $publishedAt
 * @var string               $timezone
 * @var string               $action
 * @var string|null          $previewUrl
 * @var string|null          $publicUrl
 * @var list<array<string,mixed>> $media
 * @var array<string,string> $labels
 * @var array<string,mixed>  $old
 */

use Noblogs\Core\Csrf;
use Noblogs\Core\Url;
use Noblogs\Support\Dates;

$base = '/dashboard/' . $blog->subdomain;

// Dopo un salvataggio rifiutato i valori arrivano da $old; se $old è vuoto
// significa che la pagina è stata aperta normalmente e vale il post salvato.
$hasOld = $old !== [];
$field = static fn(string $key, ?string $fallback): string => $hasOld
    ? (string) ($old[$key] ?? '')
    : (string) ($fallback ?? '');
$flag = static fn(string $key, bool $fallback): bool => $hasOld ? isset($old[$key]) : $fallback;

$this->layout('layouts/dashboard');
?>
<form method="post" action="<?= e($action) ?>" class="post-form">
  <?= Csrf::field() ?>

  <header class="dash-head editor-head">
    <div class="field field-title">
      <label class="visually-hidden" for="title"><?= e(__('post.form.title')) ?></label>
      <input type="text" id="title" name="title" maxlength="200" required
             data-slug-source="slug"
             placeholder="<?= e(__('post.form.title_placeholder')) ?>"
             value="<?= e($field('title', $post->title)) ?>">
    </div>

    <div class="editor-head-actions">
      <button type="submit" class="primary" title="<?= e(__('editor.save_shortcut')) ?>">
        <?= e(__('form.save')) ?>
      </button>
      <?php if ($publicUrl !== null && $post->isVisible()): ?>
        <a class="button" href="<?= e($publicUrl) ?>" rel="noopener"><?= e(__('post.action.view')) ?></a>
      <?php endif; ?>
    </div>
  </header>

  <?php $this->partial('dashboard/partials/editor', [
      'blog'      => $blog,
      'name'      => 'content',
      'value'     => $field('content', $post->content),
      'draftKey'  => 'blog-' . $blog->id . '-post-' . $post->id,
      'updatedAt' => Dates::parse($post->updated_at)?->getTimestamp() ?? 0,
      'postId'    => $post->id,
      'media'     => $media,
      'labels'    => $labels,
      'label'     => __('post.form.content'),
      'rows'      => 26,
  ]); ?>

  <details class="panel meta-panel" <?= $hasOld ? 'open' : '' ?>>
    <summary><?= e(__('post.form.metadata')) ?></summary>

    <div class="field-grid">
      <div class="field">
        <label for="slug"><?= e(__('post.form.slug')) ?></label>
        <input type="text" id="slug" name="slug" maxlength="200"
               autocapitalize="none" autocomplete="off" spellcheck="false"
               value="<?= e($field('slug', $post->slug)) ?>">
        <p class="hint"><?= e(__('post.form.slug_hint')) ?></p>
      </div>

      <div class="field">
        <label for="published_at"><?= e(__('post.form.published_at')) ?></label>
        <input type="datetime-local" id="published_at" name="published_at"
               value="<?= e($field('published_at', $publishedAt)) ?>">
        <p class="hint"><?= e(__('post.form.timezone_hint', ['zone' => $timezone])) ?></p>
      </div>

      <div class="field field-wide">
        <label for="tags"><?= e(__('post.form.tags')) ?></label>
        <input type="text" id="tags" name="tags"
               value="<?= e($field('tags', implode(', ', $post->tagList()))) ?>"
               placeholder="<?= e(__('post.form.tags_placeholder')) ?>">
        <p class="hint"><?= e(__('post.form.tags_hint')) ?></p>
      </div>

      <div class="field field-wide">
        <label for="meta_description"><?= e(__('post.form.description')) ?></label>
        <textarea id="meta_description" name="meta_description" rows="2" maxlength="300"><?= e($field('meta_description', $post->meta_description)) ?></textarea>
        <p class="hint"><?= e(__('post.form.description_hint')) ?></p>
      </div>

      <div class="field field-wide">
        <label for="meta_image"><?= e(__('post.form.image')) ?></label>
        <input type="text" id="meta_image" name="meta_image" maxlength="300"
               value="<?= e($field('meta_image', $post->meta_image)) ?>">
        <p class="hint"><?= e(__('post.form.image_hint')) ?></p>
      </div>

      <div class="field">
        <label for="canonical_url"><?= e(__('post.form.canonical')) ?></label>
        <input type="url" id="canonical_url" name="canonical_url" maxlength="300"
               value="<?= e($field('canonical_url', $post->canonical_url)) ?>">
        <p class="hint"><?= e(__('post.form.canonical_hint')) ?></p>
      </div>

      <div class="field">
        <label for="alias"><?= e(__('post.form.alias')) ?></label>
        <input type="text" id="alias" name="alias" maxlength="200"
               autocapitalize="none" autocomplete="off" spellcheck="false"
               value="<?= e($field('alias', $post->alias)) ?>">
        <p class="hint"><?= e(__('post.form.alias_hint')) ?></p>
      </div>

      <div class="field">
        <label for="class_name"><?= e(__('post.form.css_class')) ?></label>
        <input type="text" id="class_name" name="class_name" maxlength="200"
               value="<?= e($field('class_name', $post->class_name)) ?>">
        <p class="hint"><?= e(__('post.form.css_class_hint')) ?></p>
      </div>

      <div class="field">
        <label for="lang"><?= e(__('post.form.lang')) ?></label>
        <input type="text" id="lang" name="lang" maxlength="10"
               placeholder="<?= e($blog->displayLang()) ?>"
               value="<?= e($field('lang', $post->lang)) ?>">
        <p class="hint"><?= e(__('post.form.lang_hint')) ?></p>
      </div>
    </div>

    <ul class="checkbox-list">
      <li>
        <label>
          <input type="checkbox" name="is_published" value="1" <?= $flag('is_published', $post->is_published) ? 'checked' : '' ?>>
          <?= e(__('post.form.is_published')) ?>
        </label>
        <p class="hint"><?= e(__('post.form.is_published_hint')) ?></p>
      </li>
      <li>
        <label>
          <input type="checkbox" name="is_page" value="1" <?= $flag('is_page', $post->is_page) ? 'checked' : '' ?>>
          <?= e(__('post.form.is_page')) ?>
        </label>
        <p class="hint"><?= e(__('post.form.is_page_hint')) ?></p>
      </li>
      <li>
        <label>
          <input type="checkbox" name="make_discoverable" value="1" <?= $flag('make_discoverable', $post->make_discoverable) ? 'checked' : '' ?>>
          <?= e(__('post.form.discoverable')) ?>
        </label>
        <p class="hint"><?= e(__('post.form.discoverable_hint')) ?></p>
      </li>
    </ul>
  </details>

  <div class="actions">
    <button type="submit" class="primary"><?= e(__('form.save')) ?></button>
    <a href="<?= e(Url::to($base . ($post->is_page ? '/pagine' : '/articoli'))) ?>"><?= e(__('form.cancel')) ?></a>
  </div>
</form>

<?php if ($post->exists()): ?>
  <section class="panel">
    <h2><?= e(__('post.preview_link_title')) ?></h2>
    <p class="note"><?= e(__('post.preview_link_intro')) ?></p>
    <p class="copy-row">
      <label class="visually-hidden" for="preview-link"><?= e(__('post.preview_link_title')) ?></label>
      <input type="text" id="preview-link" readonly value="<?= e($previewUrl ?? '') ?>">
      <button type="button" class="button" data-copy="preview-link" data-copied="<?= e(__('editor.copied')) ?>"><?= e(__('editor.copy')) ?></button>
    </p>
    <p><a href="<?= e($previewUrl ?? '') ?>" rel="noopener"><?= e(__('post.preview_link_open')) ?></a></p>
  </section>

  <section class="panel danger-panel">
    <h2><?= e(__('post.danger_title')) ?></h2>
    <div class="danger-row">
      <form method="post" action="<?= e(Url::to($base . '/articoli/' . $post->id . '/duplica')) ?>">
        <?= Csrf::field() ?>
        <button type="submit" class="button"><?= e(__('post.action.duplicate')) ?></button>
      </form>

      <details class="danger-details">
        <summary><?= e(__('post.delete_button')) ?></summary>
        <form method="post" action="<?= e(Url::to($base . '/articoli/' . $post->id . '/elimina')) ?>">
          <?= Csrf::field() ?>
          <p><?= e(__('post.delete_confirm', ['title' => $post->title])) ?></p>
          <button type="submit" class="danger"><?= e(__('post.delete_button')) ?></button>
        </form>
      </details>
    </div>
  </section>
<?php endif; ?>
<?php
$this->start('scripts');
?>
<script src="<?= e(Url::asset('js/editor.js')) ?>" defer></script>
<?php
$this->end();
