<?php
/**
 * Registro di moderazione.
 *
 * @var \Noblogs\Core\View $this
 * @var list<array<string,mixed>> $entries
 */

use Noblogs\Core\I18n;
use Noblogs\Support\Dates;

$this->layout('layouts/admin');

$since = static fn(?string $value): string => Dates::since(Dates::parse($value), I18n::locale());

$this->start('title');
echo __('admin.log.title');
$this->end();

$this->start('intro');
echo __('admin.log.intro');
$this->end();

?>

<?php if ($entries === []): ?>
  <p class="admin-empty"><?= e(__('admin.log.empty')) ?></p>
<?php else: ?>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th><?= e(__('admin.log.col.when')) ?></th>
          <th><?= e(__('admin.log.col.actor')) ?></th>
          <th><?= e(__('admin.log.col.action')) ?></th>
          <th><?= e(__('admin.log.col.blog')) ?></th>
          <th><?= e(__('admin.log.col.note')) ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($entries as $entry): ?>
        <tr>
          <td class="is-quiet">
            <time datetime="<?= e(str_replace(' ', 'T', (string) $entry['created_at']) . 'Z') ?>">
              <?= e($since((string) $entry['created_at'])) ?>
            </time>
          </td>
          <td><?= e((string) ($entry['actor_email'] ?? __('admin.log.system'))) ?></td>
          <td class="admin-mono"><?= e((string) $entry['action']) ?></td>
          <td class="admin-mono"><?= e((string) ($entry['subdomain'] ?? '—')) ?></td>
          <td><?= e((string) ($entry['note'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
