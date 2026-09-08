<?php
/**
 * @var \Noblogs\Core\View    $this
 * @var \Noblogs\Models\Blog  $blog
 * @var \Noblogs\Models\Post  $post
 * @var string                $contentHtml
 */

use Noblogs\Core\Csrf;
use Noblogs\Core\Url;
use Noblogs\Markdown\Renderer;
use Noblogs\Support\Dates;

$showMeta = $showMeta ?? true;
$showUpvote = $showUpvote ?? false;
$upvotesEnabled = $upvotesEnabled ?? false;
$isDraft = $isDraft ?? false;

$this->layout('layouts/site');
$this->start('content');
?>
<article class="post-full">

<?php if ($isDraft): ?>
  <p class="draft-notice"><?= e(__('post.draft_notice')) ?></p>
<?php endif; ?>

  <h1 class="post-title"><?= Renderer::inline($post->title) ?></h1>

<?php if ($showMeta): ?>
  <p class="post-meta">
    <?php $published = $post->publishedAt(); ?>
    <?php if ($published !== null): ?>
      <time datetime="<?= e($published->format('c')) ?>"><?= e(Dates::format($published, $blog->date_format, $blog->locale())) ?></time>
    <?php endif; ?>
  </p>
<?php endif; ?>

  <div class="post-content"><?= $contentHtml ?></div>

<style>
.upvote { margin-top: 1.5em; }
.upvote[hidden] { display: none !important; }
.upvote-button[aria-pressed="true"] {
  font-weight: 700;
  box-shadow: inset 0 0 0 2px currentColor;
}
</style>

<?php $tags = $post->tagList(); ?>
<?php if ($tags !== []): ?>
  <ul class="post-tags">
    <?php foreach ($tags as $tag): ?>
      <li><a href="<?= e(Url::site('/' . trim($blog->blog_path, '/') . '/') . '?tag=' . rawurlencode($tag)) ?>"><?= e($tag) ?></a></li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<?php if ($showUpvote): ?>
  <div class="upvote"<?= $upvotesEnabled ? '' : ' hidden' ?>>
    <button class="upvote-button" type="button"
            data-uid="<?= e($post->uid) ?>"
            data-token="<?= e(Csrf::sign('upvote:' . $post->uid, 43200)) ?>"
            aria-pressed="false"
            aria-label="<?= e(__('post.upvote_label')) ?>">
      <span class="upvote-icon" aria-hidden="true">▲</span>
      <span class="upvote-count"><?= e((string) $post->effectiveUpvotes()) ?></span>
    </button>
  </div>
<?php endif; ?>

</article>
<?php
$this->end();
