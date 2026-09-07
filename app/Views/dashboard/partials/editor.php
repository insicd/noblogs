<?php
/**
 * Area di scrittura in markdown.
 *
 * Il markup che serve senza JavaScript è tutto qui: etichetta, textarea e
 * pulsante di invio del form che la contiene. Barra degli strumenti, pannello
 * dei file, anteprima e contatore vengono aggiunti da editor.js, così una
 * pagina senza script resta un modulo che funziona.
 *
 * @var \Noblogs\Models\Blog $blog
 * @var string               $name       Nome del campo.
 * @var string               $value      Markdown corrente.
 * @var string               $draftKey   Chiave del salvataggio automatico.
 * @var int                  $updatedAt  Ultimo salvataggio sul server (epoch).
 * @var int                  $postId     0 per la homepage o per un post nuovo.
 * @var list<array<string,mixed>> $media
 * @var array<string,string> $labels
 * @var string               $label      Etichetta della textarea.
 * @var int                  $rows
 */

use Noblogs\Core\Csrf;
use Noblogs\Core\Url;

$id = 'editor-' . preg_replace('/[^a-z0-9-]/', '', $name);
$rows = $rows ?? 24;
$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
?>
<div class="editor"
     data-editor
     data-target="<?= e($id) ?>"
     data-preview="<?= e(Url::to('/dashboard/' . $blog->subdomain . '/anteprima')) ?>"
     data-upload="<?= e(Url::to('/dashboard/' . $blog->subdomain . '/file')) ?>"
     data-csrf="<?= e(Csrf::token()) ?>"
     data-post-id="<?= e((string) $postId) ?>"
     data-draft-key="<?= e($draftKey) ?>"
     data-updated="<?= e((string) $updatedAt) ?>"
     data-media="<?= e((string) json_encode($media, $flags)) ?>"
     data-labels="<?= e((string) json_encode($labels, $flags)) ?>">

  <label class="editor-label" for="<?= e($id) ?>"><?= e($label) ?></label>

  <textarea id="<?= e($id) ?>" name="<?= e($name) ?>" class="editor-area"
            rows="<?= e((string) $rows) ?>" spellcheck="true"
            autocapitalize="sentences" autocomplete="off"><?= e($value) ?></textarea>

  <p class="editor-hint">
    <?= e(__('editor.hint')) ?>
    <a href="<?= e(Url::to('/aiuto/markdown')) ?>"><?= e(__('editor.syntax_help')) ?></a>
  </p>
</div>
