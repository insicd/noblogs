<?php
/**
 * Conferma dell'eliminazione di un blog.
 *
 * L'inventario di ciò che sparisce sta prima del modulo, e il pulsante non
 * fa nulla finché il sottodominio non è stato ridigitato: è l'unico modo per
 * distinguere un'eliminazione voluta da un clic sul pulsante sbagliato.
 *
 * @var \Noblogs\Core\View        $this
 * @var \Noblogs\Models\Blog      $blog
 * @var \Noblogs\Models\User|null $owner
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
echo __('admin.blogs.delete.title', ['blog' => $blog->subdomain]);
$this->end();

?>

<?php if ($mismatch): ?>
  <p class="admin-flash admin-flash--error"><?= e(__('admin.blogs.delete.mismatch')) ?></p>
<?php endif; ?>

<div class="admin-danger-zone">
  <h2><?= e(__('admin.blogs.delete.heading', ['blog' => $blog->title])) ?></h2>
  <p><?= e(__('admin.blogs.delete.warning')) ?></p>

  <ul class="admin-inventory">
    <li><span><?= e(__('admin.blogs.delete.item.address')) ?></span><span class="admin-inventory__value admin-mono"><?= e($blog->subdomain) ?></span></li>
    <li><span><?= e(__('admin.blogs.delete.item.owner')) ?></span><span class="admin-inventory__value"><?= e($owner?->email ?? '—') ?></span></li>
    <li><span><?= e(__('admin.blogs.delete.item.posts')) ?></span><span class="admin-inventory__value"><?= e($number($footprint['posts'])) ?></span></li>
    <li><span><?= e(__('admin.blogs.delete.item.pages')) ?></span><span class="admin-inventory__value"><?= e($number($footprint['pages'])) ?></span></li>
    <li><span><?= e(__('admin.blogs.delete.item.files')) ?></span><span class="admin-inventory__value"><?= e($number($footprint['files'])) ?></span></li>
    <li><span><?= e(__('admin.blogs.delete.item.storage')) ?></span><span class="admin-inventory__value"><?= e(Media::humanBytes($footprint['storage'])) ?></span></li>
    <li><span><?= e(__('admin.blogs.delete.item.subscribers')) ?></span><span class="admin-inventory__value"><?= e($number($footprint['subscribers'])) ?></span></li>
    <li><span><?= e(__('admin.blogs.delete.item.reads')) ?></span><span class="admin-inventory__value"><?= e($number($footprint['reads'])) ?></span></li>
  </ul>

  <p class="admin-hint"><?= e(__('admin.blogs.delete.files_note', ['path' => 'public/media/' . $blog->subdomain . '/'])) ?></p>
  <p class="admin-hint"><?= e(__('admin.blogs.delete.alternative')) ?></p>

  <form method="post" action="<?= e(Url::to('/admin/blog/' . $blog->id . '/azione')) ?>" class="admin-form">
    <?= Csrf::field() ?>
    <input type="hidden" name="azione" value="elimina">

    <div class="admin-field">
      <label for="nota"><?= e(__('admin.note.label')) ?></label>
      <input type="text" id="nota" name="nota" maxlength="500" value="<?= e($note) ?>">
    </div>

    <div class="admin-field">
      <label for="conferma"><?= e(__('admin.blogs.delete.type_subdomain', ['blog' => $blog->subdomain])) ?></label>
      <input type="text" id="conferma" name="conferma" class="admin-confirm-input"
             autocomplete="off" autocapitalize="off" spellcheck="false" required>
    </div>

    <button type="submit" class="admin-btn admin-btn--danger"><?= e(__('admin.blogs.delete.confirm_button')) ?></button>
    <a class="admin-btn" href="<?= e(Url::to('/admin/blog')) ?>"><?= e(__('form.cancel')) ?></a>
  </form>
</div>
