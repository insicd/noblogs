<?php
/**
 * Paginazione degli elenchi. I filtri attivi viaggiano nella query string, così
 * cambiare pagina non li perde.
 *
 * @var string              $base   Percorso della pagina, senza query.
 * @var array<string,string> $query Filtri da conservare.
 * @var int                 $page
 * @var int                 $pages
 */

use Noblogs\Core\Url;

if ($pages <= 1) {
    return;
}

$link = static function (int $number) use ($base, $query): string {
    return Url::withQuery(Url::to($base), $query + ['pagina' => (string) $number]);
};
?>
<nav class="admin-pagination" aria-label="<?= e(__('admin.pagination.label')) ?>">
  <?php if ($page > 1): ?>
    <a class="admin-btn" href="<?= e($link($page - 1)) ?>"><?= e(__('admin.pagination.previous')) ?></a>
  <?php endif; ?>
  <span><?= e(__('admin.pagination.position', ['current' => $page, 'total' => $pages])) ?></span>
  <?php if ($page < $pages): ?>
    <a class="admin-btn" href="<?= e($link($page + 1)) ?>"><?= e(__('admin.pagination.next')) ?></a>
  <?php endif; ?>
</nav>
