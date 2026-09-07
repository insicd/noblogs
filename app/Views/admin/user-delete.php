<?php
/**
 * Conferma dell'eliminazione di un account.
 *
 * Eliminare un utente porta via anche i suoi blog: l'elenco è qui perché la
 * conseguenza sia visibile prima e non dopo.
 *
 * @var \Noblogs\Core\View   $this
 * @var \Noblogs\Models\User $account
 * @var list<\Noblogs\Models\Blog> $blogs
 * @var array{posts:int,pages:int,files:int,storage:int,subscribers:int,reads:int} $footprint
 * @var string $note
 * @var bool   $mismatch
 */

use Noblogs\Core\Csrf;
use Noblogs\Core\Url;
use Noblogs\Models\Media;

$this->layout('layouts/admin');

$number = static fn(int $value): string => number_format($value, 0, ',', '.');

$this->start('title');
echo __('admin.users.delete.title', ['email' => $account->email]);
$this->end();

?>

<?php if ($mismatch): ?>
  <p class="admin-flash admin-flash--error"><?= e(__('admin.users.delete.mismatch')) ?></p>
<?php endif; ?>

<div class="admin-danger-zone">
  <h2><?= e(__('admin.users.delete.heading', ['email' => $account->email])) ?></h2>
  <p><?= e(__('admin.users.delete.warning')) ?></p>

  <ul class="admin-inventory">
    <li><span><?= e(__('admin.users.delete.item.blogs')) ?></span><span class="admin-inventory__value"><?= e($number(count($blogs))) ?></span></li>
    <li><span><?= e(__('admin.users.delete.item.posts')) ?></span><span class="admin-inventory__value"><?= e($number($footprint['posts'] + $footprint['pages'])) ?></span></li>
    <li><span><?= e(__('admin.users.delete.item.files')) ?></span><span class="admin-inventory__value"><?= e($number($footprint['files'])) ?></span></li>
    <li><span><?= e(__('admin.users.delete.item.storage')) ?></span><span class="admin-inventory__value"><?= e(Media::humanBytes($footprint['storage'])) ?></span></li>
    <li><span><?= e(__('admin.users.delete.item.subscribers')) ?></span><span class="admin-inventory__value"><?= e($number($footprint['subscribers'])) ?></span></li>
  </ul>

  <?php if ($blogs !== []): ?>
    <p><strong><?= e(__('admin.users.delete.blogs_list')) ?></strong></p>
    <ul class="admin-titles">
      <?php foreach ($blogs as $blog): ?>
        <li><span class="admin-mono"><?= e($blog->subdomain) ?></span> — <?= e($blog->title) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <p class="admin-hint"><?= e(__('admin.users.delete.alternative')) ?></p>

  <form method="post" action="<?= e(Url::to('/admin/utenti/' . $account->id . '/azione')) ?>" class="admin-form">
    <?= Csrf::field() ?>
    <input type="hidden" name="azione" value="elimina">

    <div class="admin-field">
      <label for="nota"><?= e(__('admin.note.label')) ?></label>
      <input type="text" id="nota" name="nota" maxlength="500" value="<?= e($note) ?>">
    </div>

    <div class="admin-field">
      <label for="conferma"><?= e(__('admin.users.delete.type_email', ['email' => $account->email])) ?></label>
      <input type="text" id="conferma" name="conferma" class="admin-confirm-input"
             autocomplete="off" autocapitalize="off" spellcheck="false" required>
    </div>

    <button type="submit" class="admin-btn admin-btn--danger"><?= e(__('admin.users.delete.confirm_button')) ?></button>
    <a class="admin-btn" href="<?= e(Url::to('/admin/utenti')) ?>"><?= e(__('form.cancel')) ?></a>
  </form>
</div>
