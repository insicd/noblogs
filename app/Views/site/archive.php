<?php
/**
 * @var \Noblogs\Core\View       $this
 * @var \Noblogs\Models\Blog     $blog
 * @var list<\Noblogs\Models\Post> $posts
 */

use Noblogs\Core\Url;
use Noblogs\Support\Dates;

$listingUrl = Url::site('/' . trim($blog->blog_path, '/') . '/');
$tagQuery = $includeTags !== [] ? '?tag=' . rawurlencode(implode(',', $includeTags)) : '';

$this->layout('layouts/site');
$this->start('content');
?>
<h1 class="archive-heading"><?= e($heading) ?></h1>

<?php if ($includeTags !== []): ?>
  <p class="filter-notice">
    <?= e(__('blog.filtered_by', ['tags' => implode(', ', $includeTags)])) ?>
    · <a href="<?= e($listingUrl) ?>"><?= e(__('blog.remove_filter')) ?></a>
  </p>
<?php endif; ?>

<?php if ($posts === []): ?>
  <p class="post-list-empty"><?= e(__('blog.no_posts')) ?></p>
<?php else: ?>
  <ul class="post-list">
  <?php foreach ($posts as $post): ?>
    <?php $published = $post->publishedAt(); ?>
    <li>
      <?php if ($published !== null): ?>
        <time datetime="<?= e($published->format('c')) ?>"><?= e(Dates::format($published, $blog->date_format, $blog->locale())) ?></time>
      <?php endif; ?>
      <a href="<?= e(Url::site('/' . $post->slug . '/')) ?>"><?= e($post->title) ?></a>
    </li>
  <?php endforeach; ?>
  </ul>
<?php endif; ?>

<?php if ($lastPage > 1): ?>
  <nav class="pagination" aria-label="<?= e(__('blog.pagination')) ?>">
    <?php if ($currentPage > 1): ?>
      <a rel="prev" href="<?= e($listingUrl . $tagQuery . ($tagQuery !== '' ? '&' : '?') . 'pagina=' . ($currentPage - 1)) ?>">← <?= e(__('blog.newer')) ?></a>
    <?php else: ?>
      <span></span>
    <?php endif; ?>

    <span class="pagination-info"><?= e(__('blog.page_of', ['current' => $currentPage, 'total' => $lastPage])) ?></span>

    <?php if ($currentPage < $lastPage): ?>
      <a rel="next" href="<?= e($listingUrl . $tagQuery . ($tagQuery !== '' ? '&' : '?') . 'pagina=' . ($currentPage + 1)) ?>"><?= e(__('blog.older')) ?> →</a>
    <?php else: ?>
      <span></span>
    <?php endif; ?>
  </nav>
<?php endif; ?>

<?php if ($availableTags !== [] && $includeTags === []): ?>
  <ul class="tag-cloud">
    <?php foreach ($availableTags as $tag): ?>
      <li><a href="<?= e($listingUrl . '?tag=' . rawurlencode($tag)) ?>"><?= e($tag) ?></a></li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
<?php
$this->end();
