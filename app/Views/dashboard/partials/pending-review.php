<?php
/**
 * Avviso sul blog in attesa di revisione, con l'indirizzo che risponde
 * prima che il terzo livello venga creato.
 *
 * @var \Noblogs\Models\Blog $blog
 * @var string               $class
 */

use Noblogs\Core\Url;

$class = $class ?? 'note';
?>
<?php if (!$blog->isIndexable()): ?>
  <p class="<?= e($class) ?>">
    <?= e(__('dashboard.pending_review')) ?>
    <?php if (Url::pathUntilReview($blog)): ?>
      <?= e(__('dashboard.pending_review_url', [
          'path'      => Url::pathRoot($blog),
          'subdomain' => Url::subdomainRoot($blog),
      ])) ?>
    <?php endif; ?>
  </p>
<?php endif; ?>
