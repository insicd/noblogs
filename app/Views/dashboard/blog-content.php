<?php
/**
 * Titolo e markdown della homepage del blog.
 *
 * @var \Noblogs\Core\View   $this
 * @var \Noblogs\Models\Blog $blog
 * @var list<array<string,mixed>> $media
 * @var array<string,string> $labels
 * @var array<string,mixed>  $old
 */

use Noblogs\Core\Csrf;
use Noblogs\Core\Url;
use Noblogs\Support\Dates;

$this->layout('layouts/dashboard');
?>
<header class="dash-head">
  <h1><?= e(__('blog.content.title')) ?></h1>
  <p class="dash-head-note"><?= e(__('blog.content.intro')) ?></p>
</header>

<form method="post" action="<?= e(Url::to('/dashboard/' . $blog->subdomain . '/contenuto')) ?>" class="panel form">
  <?= Csrf::field() ?>

  <div class="field">
    <label for="title"><?= e(__('blog.content.field_title')) ?></label>
    <input type="text" id="title" name="title" maxlength="200" required
           value="<?= e($old['title'] ?? $blog->title) ?>">
  </div>

  <?php $this->partial('dashboard/partials/editor', [
      'blog'      => $blog,
      'name'      => 'content',
      'value'     => $old['content'] ?? ($blog->content ?? ''),
      'draftKey'  => 'blog-' . $blog->id . '-home',
      'updatedAt' => Dates::parse($blog->updated_at)?->getTimestamp() ?? 0,
      'postId'    => 0,
      'media'     => $media,
      'labels'    => $labels,
      'label'     => __('blog.content.field_content'),
      'rows'      => 22,
  ]); ?>

  <div class="actions">
    <button type="submit" class="primary"><?= e(__('form.save')) ?></button>
    <a href="<?= e(Url::blogRoot($blog)) ?>" rel="noopener"><?= e(__('dashboard.visit')) ?> ↗</a>
  </div>
</form>

<section class="panel">
  <h2><?= e(__('editor.directives_title')) ?></h2>
  <p class="note"><?= e(__('editor.directives_intro')) ?></p>
  <ul class="directive-list">
    <li><code>{{ posts|limit:5 }}</code> <span><?= e(__('editor.directive_posts')) ?></span></li>
    <li><code>{{ tags }}</code> <span><?= e(__('editor.directive_tags')) ?></span></li>
    <li><code>{{ archivio }}</code> <span><?= e(__('editor.directive_archive')) ?></span></li>
    <li><code>{{ cerca }}</code> <span><?= e(__('editor.directive_search')) ?></span></li>
    <li><code>{{ iscrizione }}</code> <span><?= e(__('editor.directive_subscribe')) ?></span></li>
  </ul>
</section>
<?php
$this->start('scripts');
?>
<script src="<?= e(Url::asset('js/editor.js')) ?>" defer></script>
<?php
$this->end();
