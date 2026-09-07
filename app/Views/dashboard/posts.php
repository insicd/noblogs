<?php
/**
 * Elenco degli articoli o delle pagine.
 *
 * @var \Noblogs\Core\View   $this
 * @var \Noblogs\Models\Blog $blog
 * @var \Noblogs\Models\User $user
 * @var list<\Noblogs\Models\Post> $posts
 * @var array{all:int,published:int,draft:int,scheduled:int} $counts
 * @var bool                 $isPages
 * @var string               $status
 * @var string               $query
 * @var string               $newUrl
 */

use Noblogs\Core\Csrf;
use Noblogs\Core\Url;
use Noblogs\Support\Dates;

$base = '/dashboard/' . $blog->subdomain;
$listUrl = Url::to($base . ($isPages ? '/pagine' : '/articoli'));

$filters = [
    ''             => __('post.filter.all') . ' (' . $counts['all'] . ')',
    'pubblicati'   => __('post.filter.published') . ' (' . $counts['published'] . ')',
    'bozze'        => __('post.filter.drafts') . ' (' . $counts['draft'] . ')',
    'programmati'  => __('post.filter.scheduled') . ' (' . $counts['scheduled'] . ')',
];

$this->layout('layouts/dashboard');
?>
<header class="dash-head">
  <h1><?= e($isPages ? __('post.pages_title') : __('post.posts_title')) ?></h1>
  <p class="dash-head-actions">
    <a class="button primary" href="<?= e($newUrl) ?>">
      <?= e($isPages ? __('dashboard.new_page') : __('dashboard.new_post')) ?>
    </a>
  </p>
</header>

<div class="toolbar-row">
  <ul class="filter-tabs">
    <?php foreach ($filters as $value => $label): ?>
      <li>
        <a href="<?= e($listUrl . ($value !== '' ? '?stato=' . $value : '')) ?>"
           class="<?= $status === $value ? 'current' : '' ?>"><?= e($label) ?></a>
      </li>
    <?php endforeach; ?>
  </ul>

  <form method="get" action="<?= e($listUrl) ?>" class="inline-search" role="search">
    <?php if ($status !== ''): ?>
      <input type="hidden" name="stato" value="<?= e($status) ?>">
    <?php endif; ?>
    <label class="visually-hidden" for="q"><?= e(__('post.search_label')) ?></label>
    <input type="search" id="q" name="q" value="<?= e($query) ?>"
           placeholder="<?= e(__('post.search_placeholder')) ?>">
    <button type="submit"><?= e(__('post.search_button')) ?></button>
  </form>
</div>

<?php if ($posts === []): ?>
  <div class="dash-empty">
    <p><?= e($query !== '' ? __('post.no_matches') : __('post.none_yet')) ?></p>
    <p><a class="button" href="<?= e($newUrl) ?>"><?= e($isPages ? __('dashboard.new_page') : __('dashboard.new_post')) ?></a></p>
  </div>
<?php else: ?>
  <table class="data-table">
    <thead>
      <tr>
        <th scope="col"><?= e(__('post.column.title')) ?></th>
        <th scope="col"><?= e(__('post.column.status')) ?></th>
        <th scope="col"><?= e(__('post.column.date')) ?></th>
        <th scope="col"><?= e(__('post.column.tags')) ?></th>
        <th scope="col"><?= e(__('post.column.actions')) ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($posts as $post): ?>
        <?php $published = $post->publishedAt(); ?>
        <tr>
          <td>
            <a class="row-title" href="<?= e(Url::to($base . '/articoli/' . $post->id)) ?>"><?= e($post->title) ?></a>
            <span class="row-slug">/<?= e($post->slug) ?></span>
          </td>
          <td><?php $this->partial('dashboard/partials/post-status', ['post' => $post]); ?></td>
          <td>
            <?php if ($published !== null): ?>
              <time datetime="<?= e($published->format('c')) ?>">
                <?= e(Dates::format(Dates::toLocal($published, $user->timezone), 'j M Y H:i', $user->locale)) ?>
              </time>
            <?php endif; ?>
          </td>
          <td class="cell-tags"><?= e(implode(', ', $post->tagList())) ?></td>
          <td class="cell-actions">
            <a href="<?= e(Url::to($base . '/articoli/' . $post->id)) ?>"><?= e(__('post.action.edit')) ?></a>

            <?php if ($post->isVisible()): ?>
              <a href="<?= e(Url::post($blog, $post->slug)) ?>" rel="noopener"><?= e(__('post.action.view')) ?></a>
            <?php else: ?>
              <a href="<?= e(Url::post($blog, $post->slug) . '?token=' . $post->previewToken()) ?>" rel="noopener">
                <?= e(__('post.action.preview')) ?>
              </a>
            <?php endif; ?>

            <form method="post" action="<?= e(Url::to($base . '/articoli/' . $post->id . '/duplica')) ?>" class="inline-form">
              <?= Csrf::field() ?>
              <button type="submit" class="link"><?= e(__('post.action.duplicate')) ?></button>
            </form>

            <details class="danger-details">
              <summary><?= e(__('form.delete')) ?></summary>
              <form method="post" action="<?= e(Url::to($base . '/articoli/' . $post->id . '/elimina')) ?>">
                <?= Csrf::field() ?>
                <p><?= e(__('post.delete_confirm', ['title' => $post->title])) ?></p>
                <button type="submit" class="danger"><?= e(__('post.delete_button')) ?></button>
              </form>
            </details>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
