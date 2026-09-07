<?php
/**
 * Ricerca nella vetrina.
 *
 * @var \Noblogs\Core\View $this
 * @var list<array{post:\Noblogs\Models\Post,blog:\Noblogs\Models\Blog}> $entries
 * @var string $query
 */

use Noblogs\Core\Url;

$this->layout('layouts/platform');
$this->start('content');
?>
<div class="pf-shell">
  <h1><?= e(__('discover.search_title')) ?></h1>

  <form class="pf-search" method="get" action="<?= e(Url::to('/esplora/cerca')) ?>" role="search">
    <label for="nb-search"><?= e(__('discover.search_label')) ?></label>
    <input type="search" id="nb-search" name="q" value="<?= e($query) ?>"
           placeholder="<?= e(__('discover.search_placeholder')) ?>" autofocus>
    <button type="submit"><?= e(__('discover.search_button')) ?></button>
  </form>

  <p class="pf-hint"><?= e(__('discover.search_hint')) ?></p>

  <?php if ($query !== ''): ?>
    <?php if ($entries === []): ?>
      <p class="pf-empty"><?= e(__('discover.search_empty', ['query' => $query])) ?></p>
    <?php else: ?>
      <h2 class="pf-results-heading"><?= e(__('discover.search_results', ['query' => $query])) ?></h2>
      <p class="pf-results-count"><?= e(__('discover.search_count', ['count' => count($entries)])) ?></p>
      <?php $this->partial('platform/partials/entries', ['entries' => $entries]); ?>
    <?php endif; ?>
  <?php endif; ?>

  <p class="pf-discover-links">
    <a href="<?= e(Url::to('/esplora')) ?>">← <?= e(__('discover.heading')) ?></a>
  </p>
</div>
<?php
$this->end();
echo $this->slot('content'); // vedi la nota sullo slot in layouts/platform.php
