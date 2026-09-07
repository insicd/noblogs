<?php
/**
 * @var \Noblogs\Core\View         $this
 * @var \Noblogs\Models\Blog       $blog
 * @var list<\Noblogs\Models\Post> $results
 * @var string                     $query
 */

use Noblogs\Core\Url;
use Noblogs\Support\Dates;

$this->layout('layouts/site');
$this->start('content');
?>
<h1 class="archive-heading"><?= e(__('search.title')) ?></h1>

<form class="search-form" method="get" action="<?= e(Url::site('/cerca/')) ?>" role="search">
  <label for="nb-search"><?= e(__('search.label')) ?></label>
  <input type="search" id="nb-search" name="q" value="<?= e($query) ?>"
         placeholder="<?= e(__('search.placeholder')) ?>" autofocus>
  <button type="submit"><?= e(__('search.button')) ?></button>
</form>

<?php if ($query !== ''): ?>
  <?php if ($results === []): ?>
    <p class="post-list-empty"><?= e(__('search.no_results', ['query' => $query])) ?></p>
  <?php else: ?>
    <p class="search-count"><?= e(__('search.result_count', ['count' => count($results)])) ?></p>
    <ul class="post-list">
    <?php foreach ($results as $post): ?>
      <?php $published = $post->publishedAt(); ?>
      <li>
        <?php if ($published !== null): ?>
          <time datetime="<?= e($published->format('c')) ?>"><?= e(Dates::format($published, $blog->date_format, $blog->locale())) ?></time>
        <?php endif; ?>
        <a href="<?= e(Url::site('/' . $post->slug . '/')) ?>"><?= e($post->title) ?></a>
        <?php $description = $post->description(); ?>
        <?php if ($description !== ''): ?>
          <p class="post-list-description"><?= e($description) ?></p>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
    </ul>
  <?php endif; ?>
<?php endif; ?>
<?php
$this->end();
