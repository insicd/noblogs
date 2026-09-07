<?php
/**
 * Dominio personalizzato, con le istruzioni DNS.
 *
 * @var \Noblogs\Core\View   $this
 * @var \Noblogs\Models\Blog $blog
 * @var bool                 $enabled
 * @var string               $target
 * @var string               $mainDomain
 * @var array<string,mixed>  $old
 */

use Noblogs\Core\Csrf;
use Noblogs\Core\Url;

$base = '/dashboard/' . $blog->subdomain;
$hasOld = $old !== [];
$current = $hasOld ? (string) ($old['domain'] ?? '') : (string) ($blog->domain ?? '');

$this->layout('layouts/dashboard');
?>
<header class="dash-head">
  <h1><?= e(__('settings.domain_title')) ?></h1>
  <p class="dash-head-note"><?= e(__('settings.domain_intro')) ?></p>
</header>

<?php if (!$enabled): ?>
  <div class="notice">
    <p><?= e(__('settings.domain_disabled')) ?></p>
  </div>
<?php else: ?>
  <form method="post" action="<?= e(Url::to($base . '/impostazioni/dominio')) ?>" class="panel form">
    <?= Csrf::field() ?>

    <div class="field">
      <label for="domain"><?= e(__('settings.domain_field')) ?></label>
      <input type="text" id="domain" name="domain" maxlength="191"
             autocapitalize="none" autocomplete="off" spellcheck="false"
             placeholder="esempio.it" value="<?= e($current) ?>">
      <p class="hint"><?= e(__('settings.domain_field_hint')) ?></p>
    </div>

    <div class="actions">
      <button type="submit" class="primary"><?= e(__('form.save')) ?></button>
      <?php if ($blog->domain !== null && $blog->domain !== ''): ?>
        <span class="hint"><?= e(__('settings.domain_remove_hint')) ?></span>
      <?php endif; ?>
    </div>
  </form>

  <section class="panel">
    <h2><?= e(__('settings.dns_title')) ?></h2>
    <p><?= e(__('settings.dns_intro')) ?></p>

    <table class="data-table">
      <thead>
        <tr>
          <th scope="col"><?= e(__('settings.dns_type')) ?></th>
          <th scope="col"><?= e(__('settings.dns_name')) ?></th>
          <th scope="col"><?= e(__('settings.dns_value')) ?></th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>CNAME</td>
          <td>www</td>
          <td class="mono"><?= e($target) ?></td>
        </tr>
        <tr>
          <td>ALIAS / ANAME</td>
          <td>@</td>
          <td class="mono"><?= e($target) ?></td>
        </tr>
      </tbody>
    </table>

    <p class="hint"><?= e(__('settings.dns_apex_note', ['domain' => $mainDomain])) ?></p>
    <p class="hint"><?= e(__('settings.dns_wait_note')) ?></p>
    <p class="hint"><?= e(__('settings.dns_https_note')) ?></p>
  </section>
<?php endif; ?>
