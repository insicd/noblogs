<?php
/**
 * La vetrina.
 *
 * @var \Noblogs\Core\View $this
 * @var list<array{post:\Noblogs\Models\Post,blog:\Noblogs\Models\Blog}> $entries
 * @var list<array{code:string,total:int}> $languages
 * @var string      $order
 * @var string|null $lang
 * @var int         $currentPage
 * @var int         $lastPage
 * @var int         $total
 */

use Noblogs\Core\Url;

$orders = [
    'score'   => __('discover.order.score'),
    'recenti' => __('discover.order.recent'),
    'caso'    => __('discover.order.random'),
];

/** Costruisce un indirizzo della vetrina conservando i filtri attivi. */
$pageUrl = static function (array $overrides = []) use ($order, $lang, $currentPage): string {
    // I valori passati vincono su quelli correnti: in PHP l'unione di array
    // conserva le chiavi dell'operando di sinistra.
    $params = array_filter($overrides + [
        'ordina' => $order !== 'score' ? $order : null,
        'lingua' => $lang,
        'pagina' => $currentPage > 1 ? $currentPage : null,
    ], static fn(mixed $value): bool => $value !== null && $value !== '');

    return Url::to('/esplora') . ($params !== [] ? '?' . http_build_query($params) : '');
};

$this->layout('layouts/platform');
$this->start('content');
?>
<div class="pf-shell">
  <h1><?= e(__('discover.heading')) ?></h1>
  <p class="pf-lead"><?= e(__('discover.intro')) ?></p>

  <form class="pf-search" method="get" action="<?= e(Url::to('/esplora/cerca')) ?>" role="search">
    <label for="nb-discover-search"><?= e(__('discover.search_label')) ?></label>
    <input type="search" id="nb-discover-search" name="q"
           placeholder="<?= e(__('discover.search_placeholder')) ?>">
    <button type="submit"><?= e(__('discover.search_button')) ?></button>
  </form>

  <nav class="pf-filters" aria-label="<?= e(__('discover.filters_label')) ?>">
    <ul class="pf-filter-group" aria-label="<?= e(__('discover.order_label')) ?>">
      <?php foreach ($orders as $key => $label): ?>
        <li>
          <a href="<?= e($pageUrl(['ordina' => $key === 'score' ? null : $key, 'pagina' => null])) ?>"
             <?= $order === $key ? 'aria-current="true"' : '' ?>><?= e($label) ?></a>
        </li>
      <?php endforeach; ?>
    </ul>

    <?php if ($languages !== []): ?>
      <ul class="pf-filter-group" aria-label="<?= e(__('discover.lang_label')) ?>">
        <li>
          <a href="<?= e($pageUrl(['lingua' => null, 'pagina' => null])) ?>"
             <?= $lang === null ? 'aria-current="true"' : '' ?>><?= e(__('discover.lang_all')) ?></a>
        </li>
        <?php foreach ($languages as $language): ?>
          <li>
            <a href="<?= e($pageUrl(['lingua' => $language['code'], 'pagina' => null])) ?>"
               <?= $lang === $language['code'] ? 'aria-current="true"' : '' ?>><?= e(strtoupper($language['code'])) ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </nav>

  <?php if ($entries === []): ?>
    <p class="pf-empty"><?= e(__('discover.empty')) ?></p>
  <?php else: ?>
    <?php $this->partial('platform/partials/entries', ['entries' => $entries]); ?>
  <?php endif; ?>

  <?php if ($lastPage > 1): ?>
    <nav class="pf-pagination" aria-label="<?= e(__('discover.pagination')) ?>">
      <?php if ($currentPage > 1): ?>
        <a rel="prev" href="<?= e($pageUrl(['pagina' => $currentPage - 1 > 1 ? $currentPage - 1 : null])) ?>">←&nbsp;<?= e(__('discover.newer')) ?></a>
      <?php else: ?>
        <span></span>
      <?php endif; ?>

      <span class="pf-pagination-info"><?= e(__('discover.page_of', ['current' => $currentPage, 'total' => $lastPage])) ?></span>

      <?php if ($currentPage < $lastPage): ?>
        <a rel="next" href="<?= e($pageUrl(['pagina' => $currentPage + 1])) ?>"><?= e(__('discover.older')) ?>&nbsp;→</a>
      <?php else: ?>
        <span></span>
      <?php endif; ?>
    </nav>
  <?php endif; ?>

  <p class="pf-discover-links">
    <a href="<?= e(Url::to('/esplora/caso')) ?>" target="_blank" rel="noopener noreferrer"><?= e(__('discover.random')) ?></a>
    <span aria-hidden="true">·</span>
    <a href="<?= e(Url::to('/esplora/caso-blog')) ?>" target="_blank" rel="noopener noreferrer"><?= e(__('discover.random_blog')) ?></a>
    <span aria-hidden="true">·</span>
    <a href="<?= e(Url::to('/esplora/feed')) ?>"><?= e(__('discover.feed')) ?></a>
  </p>
</div>
<?php
$this->end();
echo $this->slot('content'); // vedi la nota sullo slot in layouts/platform.php
