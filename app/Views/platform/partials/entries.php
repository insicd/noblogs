<?php
/**
 * Elenco di articoli della vetrina, ognuno con il blog che lo ospita.
 *
 * @var list<array{post:\Noblogs\Models\Post,blog:\Noblogs\Models\Blog}> $entries
 */

use Noblogs\Core\Url;
use Noblogs\Support\Dates;

/** @var bool $showDescription */
$showDescription = $showDescription ?? true;
?>
<ul class="pf-entries">
<?php foreach ($entries as $entry): ?>
  <?php
  $post = $entry['post'];
  $blog = $entry['blog'];
  $published = $post->publishedAt();
  $description = $showDescription ? $post->description() : '';
  ?>
  <li class="pf-entry">
    <h3 class="pf-entry-title">
      <a href="<?= e(Url::post($blog, $post->slug)) ?>"><?= e($post->title) ?></a>
    </h3>

    <p class="pf-entry-meta">
      <a class="pf-entry-blog" href="<?= e(Url::blogRoot($blog)) ?>"><?= e($blog->title) ?></a>
      <?php if ($published !== null): ?>
        <span aria-hidden="true">·</span>
        <time datetime="<?= e($published->format('c')) ?>"><?= e(Dates::format($published, 'j F Y', $blog->locale())) ?></time>
      <?php endif; ?>
      <?php if ($post->effectiveUpvotes() > 0): ?>
        <span aria-hidden="true">·</span>
        <span class="pf-entry-votes"><?= e(__('discover.upvotes', ['count' => $post->effectiveUpvotes()])) ?></span>
      <?php endif; ?>
    </p>

    <?php if ($description !== ''): ?>
      <p class="pf-entry-description"><?= e($description) ?></p>
    <?php endif; ?>

    <?php $tags = $post->tagList(); ?>
    <?php if ($tags !== []): ?>
      <ul class="pf-entry-tags">
        <?php foreach (array_slice($tags, 0, 5) as $tag): ?>
          <li><a href="<?= e(Url::to('/esplora/cerca') . '?q=' . rawurlencode($tag)) ?>"><?= e($tag) ?></a></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </li>
<?php endforeach; ?>
</ul>
