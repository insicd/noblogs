<?php
/**
 * Elenco degli utenti e azioni sul singolo account.
 *
 * @var \Noblogs\Core\View $this
 * @var list<array{user:\Noblogs\Models\User,blogs:int}> $users
 * @var string $search
 * @var string $state
 * @var string $sort
 * @var int    $total
 * @var int    $page
 * @var int    $pages
 * @var int    $adminCount
 * @var \Noblogs\Models\User $staff
 */

use Noblogs\Core\Csrf;
use Noblogs\Core\I18n;
use Noblogs\Core\Url;
use Noblogs\Support\Dates;

$this->layout('layouts/admin');

$since = static fn(?string $value): string => Dates::since(Dates::parse($value), I18n::locale());

$states = [
    'tutti'          => __('admin.users.filter.all'),
    'attivi'         => __('admin.users.filter.active'),
    'sospesi'        => __('admin.users.filter.suspended'),
    'non-verificati' => __('admin.users.filter.unverified'),
    'staff'          => __('admin.users.filter.staff'),
];

$sorts = [
    'recenti' => __('admin.users.sort.recent'),
    'accesso' => __('admin.users.sort.login'),
    'email'   => __('admin.users.sort.email'),
    'blog'    => __('admin.users.sort.blogs'),
];

$this->start('title');
echo __('admin.users.title');
$this->end();

?>

<form method="get" action="<?= e(Url::to('/admin/utenti')) ?>" class="admin-filters">
  <div class="admin-field admin-field--wide">
    <label for="q"><?= e(__('admin.users.search_label')) ?></label>
    <input type="search" id="q" name="q" value="<?= e($search) ?>" maxlength="100"
           placeholder="<?= e(__('admin.users.search_placeholder')) ?>">
  </div>
  <div class="admin-field">
    <label for="stato"><?= e(__('admin.users.filter_label')) ?></label>
    <select id="stato" name="stato">
      <?php foreach ($states as $value => $label): ?>
        <option value="<?= e($value) ?>"<?= $state === $value ? ' selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="admin-field">
    <label for="ordina"><?= e(__('admin.users.sort_label')) ?></label>
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
    ? __('admin.users.count_one')
    : __('admin.users.count', ['count' => number_format($total, 0, ',', '.')])) ?></p>
<?php if (!$staff->isAdmin()): ?>
  <p class="admin-hint"><?= e(__('admin.users.moderator_scope')) ?></p>
<?php endif; ?>

<?php if ($users === []): ?>
  <p class="admin-empty"><?= e(__('admin.users.empty')) ?></p>
<?php else: ?>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th><?= e(__('admin.users.col.email')) ?></th>
          <th><?= e(__('admin.users.col.role')) ?></th>
          <th><?= e(__('admin.users.col.blogs')) ?></th>
          <th><?= e(__('admin.users.col.state')) ?></th>
          <th><?= e(__('admin.users.col.registered')) ?></th>
          <th><?= e(__('admin.users.col.last_login')) ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($users as $entry): ?>
        <?php
        $account = $entry['user'];
        $isSelf = $account->id === $staff->id;
        // L'ultimo amministratore attivo non può essere degradato, sospeso né
        // eliminato: resterebbe un'installazione senza nessuno che può entrare.
        $isLastAdmin = $account->isAdmin() && $adminCount <= 1;
        $canTouchRoles = $staff->isAdmin() && !$isSelf && !$isLastAdmin;
        $action = Url::to('/admin/utenti/' . $account->id . '/azione');
        ?>
        <tr>
          <td>
            <?= e($account->email) ?>
            <?php if ($isSelf): ?><span class="admin-tag"><?= e(__('admin.users.you')) ?></span><?php endif; ?>
          </td>
          <td>
            <span class="admin-tag<?= $account->isModerator() ? ' admin-tag--ok' : '' ?>">
              <?= e(match ($account->role) {
                  'admin'     => __('admin.role.admin'),
                  'moderator' => __('admin.role.moderator'),
                  default     => __('admin.role.user'),
              }) ?>
            </span>
          </td>
          <td class="is-numeric"><?= e($entry['blogs'] . ' / ' . $account->max_blogs) ?></td>
          <td>
            <?php if ($account->is_active): ?>
              <span class="admin-tag admin-tag--ok"><?= e(__('admin.users.state.active')) ?></span>
            <?php else: ?>
              <span class="admin-tag admin-tag--danger"><?= e(__('admin.users.state.suspended')) ?></span>
            <?php endif; ?>
            <?php if ($account->email_verified_at === null): ?>
              <span class="admin-tag admin-tag--pending"><?= e(__('admin.users.state.unverified')) ?></span>
            <?php endif; ?>
          </td>
          <td class="is-quiet"><?= e($since($account->created_at)) ?></td>
          <td class="is-quiet"><?= e($account->last_login_at === null ? __('admin.users.never') : $since($account->last_login_at)) ?></td>
        </tr>
        <tr>
          <td colspan="6">
            <form method="post" action="<?= e($action) ?>" class="admin-actions">
              <?= Csrf::field() ?>
              <input type="text" name="nota" maxlength="500" class="admin-note"
                     placeholder="<?= e(__('admin.note.placeholder')) ?>"
                     aria-label="<?= e(__('admin.note.label')) ?>">

              <?php if ($account->is_active): ?>
                <?php if (!$isSelf && !$isLastAdmin): ?>
                  <button type="submit" name="azione" value="sospendi" class="admin-btn"><?= e(__('admin.users.action.sospendi')) ?></button>
                <?php endif; ?>
              <?php else: ?>
                <button type="submit" name="azione" value="riattiva" class="admin-btn"><?= e(__('admin.users.action.riattiva')) ?></button>
              <?php endif; ?>

              <?php if ($account->email_verified_at === null): ?>
                <button type="submit" name="azione" value="verifica-email" class="admin-btn"><?= e(__('admin.users.action.verifica_email')) ?></button>
              <?php endif; ?>

              <?php if ($canTouchRoles): ?>
                <?php if ($account->role !== 'moderator'): ?>
                  <button type="submit" name="azione" value="promuovi-moderatore" class="admin-btn"><?= e(__('admin.users.action.promuovi_moderatore')) ?></button>
                <?php endif; ?>
                <?php if ($account->role !== 'admin'): ?>
                  <button type="submit" name="azione" value="promuovi-admin" class="admin-btn"><?= e(__('admin.users.action.promuovi_admin')) ?></button>
                <?php endif; ?>
                <?php if ($account->role !== 'user'): ?>
                  <button type="submit" name="azione" value="revoca-ruolo" class="admin-btn"><?= e(__('admin.users.action.revoca_ruolo')) ?></button>
                <?php endif; ?>
                <button type="submit" name="azione" value="elimina" class="admin-btn admin-btn--danger"><?= e(__('admin.users.action.elimina')) ?></button>
              <?php endif; ?>
            </form>

            <form method="post" action="<?= e($action) ?>" class="admin-actions">
              <?= Csrf::field() ?>
              <label class="admin-hint" for="limite-<?= e((string) $account->id) ?>"><?= e(__('admin.users.limit_label')) ?></label>
              <input type="number" id="limite-<?= e((string) $account->id) ?>" name="limite" min="0" max="1000"
                     value="<?= e((string) $account->max_blogs) ?>" style="width:5rem">
              <button type="submit" name="azione" value="cambia-limite-blog" class="admin-btn"><?= e(__('admin.users.action.cambia_limite')) ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php $this->partial('admin/_pagination', [
      'base'  => '/admin/utenti',
      'query' => ['q' => $search, 'stato' => $state, 'ordina' => $sort],
      'page'  => $page,
      'pages' => $pages,
  ]); ?>
<?php endif; ?>
