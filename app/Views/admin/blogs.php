<?php
/**
 * Elenco dei blog, con ricerca, filtri e azioni di moderazione.
 *
 * @var \Noblogs\Core\View $this
 * @var list<array{blog:\Noblogs\Models\Blog,owner_email:string,owner_active:bool,posts:int}> $blogs
 * @var string $search
 * @var string $state
 * @var string $sort
 * @var int    $total
 * @var int    $page
 * @var int    $pages
 */

use Noblogs\Core\I18n;
use Noblogs\Core\Url;
use Noblogs\Models\Media;
use Noblogs\Support\Dates;

$this->layout('layouts/admin');

$since = static fn(?string $value): string => Dates::since(Dates::parse($value), I18n::locale());

$states = [
    'tutti'     => __('admin.blogs.filter.all'),
    'attesa'    => __('admin.blogs.filter.pending'),
    'approvati' => __('admin.blogs.filter.approved'),
    'nascosti'  => __('admin.blogs.filter.hidden'),
    'segnalati' => __('admin.blogs.filter.flagged'),
];

$sorts = [
    'recenti'    => __('admin.blogs.sort.recent'),
    'attivita'   => __('admin.blogs.sort.activity'),
    'rischio'    => __('admin.blogs.sort.risk'),
    'spazio'     => __('admin.blogs.sort.storage'),
    'alfabetico' => __('admin.blogs.sort.alpha'),
];

$this->start('title');
echo __('admin.blogs.title');
$this->end();

?>

<form method="get" action="<?= e(Url::to('/admin/blog')) ?>" class="admin-filters">
  <div class="admin-field admin-field--wide">
    <label for="q"><?= e(__('admin.blogs.search_label')) ?></label>
    <input type="search" id="q" name="q" value="<?= e($search) ?>" maxlength="100"
           placeholder="<?= e(__('admin.blogs.search_placeholder')) ?>">
  </div>
  <div class="admin-field">
    <label for="stato"><?= e(__('admin.blogs.filter_label')) ?></label>
    <select id="stato" name="stato">
      <?php foreach ($states as $value => $label): ?>
        <option value="<?= e($value) ?>"<?= $state === $value ? ' selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="admin-field">
    <label for="ordina"><?= e(__('admin.blogs.sort_label')) ?></label>
    <select id="ordina" name="ordina">
      <?php foreach ($sorts as $value => $label): ?>
        <option value="<?= e($value) ?>"<?= $sort === $value ? ' selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="admin-field">
    <button type="submit" class="admin-btn admin-btn--primary"><?= e(__('admin.filters.apply')) ?></button>
  </div>
</form>

<p class="admin-hint"><?= e($total === 1
    ? __('admin.blogs.count_one')
    : __('admin.blogs.count', ['count' => number_format($total, 0, ',', '.')])) ?></p>

<?php if ($blogs === []): ?>
  <p class="admin-empty"><?= e(__('admin.blogs.empty')) ?></p>
<?php else: ?>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th><?= e(__('admin.blogs.col.blog')) ?></th>
          <th><?= e(__('admin.blogs.col.owner')) ?></th>
          <th><?= e(__('admin.blogs.col.state')) ?></th>
          <th><?= e(__('admin.blogs.col.posts')) ?></th>
          <th><?= e(__('admin.blogs.col.storage')) ?></th>
          <th><?= e(__('admin.blogs.col.created')) ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($blogs as $entry): ?>
        <?php
        $blog = $entry['blog'];

        // Si mostra solo l'azione che ha senso nello stato corrente: un blog
        // già nascosto non ha bisogno del pulsante per nasconderlo.
        $actions = [];
        if (!$blog->reviewed || $blog->to_review) {
            $actions[] = 'approva';
        }
        $actions[] = $blog->hidden ? 'mostra' : 'nascondi';
        $actions[] = $blog->flagged ? 'rimuovi-segnalazione' : 'segnala';
        $actions[] = $blog->allow_raw_html ? 'revoca-html' : 'consenti-html';
        $actions[] = 'elimina';
        ?>
        <tr>
          <td>
            <strong><?= e($blog->title) ?></strong><br>
            <a class="admin-mono" href="<?= e(Url::blogRoot($blog)) ?>" target="_blank" rel="noopener noreferrer"><?= e($blog->subdomain) ?></a>
            <?php if ($blog->domain !== null && $blog->domain !== ''): ?>
              <br><span class="admin-mono admin-tag"><?= e($blog->domain) ?></span>
            <?php endif; ?>
          </td>
          <td>
            <?= e($entry['owner_email']) ?>
            <?php if (!$entry['owner_active']): ?>
              <br><span class="admin-tag admin-tag--danger"><?= e(__('admin.users.state.suspended')) ?></span>
            <?php endif; ?>
          </td>
          <td>
            <?php if (!$blog->reviewed || $blog->to_review): ?>
              <span class="admin-tag admin-tag--pending"><?= e(__('admin.blogs.state.pending')) ?></span>
            <?php else: ?>
              <span class="admin-tag admin-tag--ok"><?= e(__('admin.blogs.state.approved')) ?></span>
            <?php endif; ?>
            <?php if ($blog->hidden): ?><span class="admin-tag"><?= e(__('admin.blogs.state.hidden')) ?></span><?php endif; ?>
            <?php if ($blog->flagged): ?><span class="admin-tag admin-tag--danger"><?= e(__('admin.blogs.state.flagged')) ?></span><?php endif; ?>
            <?php if ($blog->allow_raw_html): ?><span class="admin-tag admin-tag--danger"><?= e(__('admin.blogs.state.raw_html')) ?></span><?php endif; ?>
            <?php if ($blog->reviewer_note !== null && trim($blog->reviewer_note) !== ''): ?>
              <br><span class="admin-hint"><?= e($blog->reviewer_note) ?></span>
            <?php endif; ?>
          </td>
          <td class="is-numeric"><?= e(number_format($entry['posts'], 0, ',', '.')) ?></td>
          <td class="is-numeric"><?= e(Media::humanBytes($blog->storage_used)) ?></td>
          <td class="is-quiet"><?= e($since($blog->created_at)) ?></td>
        </tr>
        <tr>
          <td colspan="6"><?php $this->partial('admin/_blog-actions', ['blog' => $blog, 'actions' => $actions]); ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php $this->partial('admin/_pagination', [
      'base'  => '/admin/blog',
      'query' => ['q' => $search, 'stato' => $state, 'ordina' => $sort],
      'page'  => $page,
      'pages' => $pages,
  ]); ?>
<?php endif; ?>
