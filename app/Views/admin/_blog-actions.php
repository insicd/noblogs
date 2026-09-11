<?php
/**
 * Barra di azioni su un blog, con la nota facoltativa del moderatore.
 *
 * Tutte le azioni passano dalla stessa rotta POST: quale sia lo dice il valore
 * del pulsante premuto.
 *
 * @var \Noblogs\Models\Blog $blog
 * @var list<string>         $actions
 */

use Noblogs\Core\Csrf;
use Noblogs\Core\Url;
?>
<form method="post" action="<?= e(Url::to('/admin/blog/' . $blog->id . '/azione')) ?>" class="admin-actions">
  <?= Csrf::field() ?>
  <input type="text" name="nota" maxlength="500" class="admin-note"
         placeholder="<?= e(__('admin.note.placeholder')) ?>"
         aria-label="<?= e(__('admin.note.label')) ?>">
  <?php foreach ($actions as $action): ?>
    <button type="submit" name="azione" value="<?= e($action) ?>"
            class="admin-btn<?= $action === 'elimina' ? ' admin-btn--danger' : '' ?>">
      <?= e(match ($action) {
          'approva'              => __('admin.blogs.action.approva'),
          'nascondi'             => __('admin.blogs.action.nascondi'),
          'mostra'               => __('admin.blogs.action.mostra'),
          'segnala'              => __('admin.blogs.action.segnala'),
          'rimuovi-segnalazione' => __('admin.blogs.action.rimuovi_segnalazione'),
          'consenti-html'        => __('admin.blogs.action.consenti_html'),
          'revoca-html'          => __('admin.blogs.action.revoca_html'),
          'terzo-livello'        => __('admin.blogs.action.terzo_livello'),
          'solo-percorso'        => __('admin.blogs.action.solo_percorso'),
          'elimina'              => __('admin.blogs.action.elimina'),
          default                => $action,
      }) ?>
    </button>
  <?php endforeach; ?>
</form>
