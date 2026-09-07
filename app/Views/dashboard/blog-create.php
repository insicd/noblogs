<?php
/**
 * @var \Noblogs\Core\View   $this
 * @var \Noblogs\Models\User $user
 * @var bool                 $canCreate
 * @var bool                 $verified
 * @var int                  $maxBlogs
 * @var int                  $blogCount
 * @var string               $domain
 * @var array<string,mixed>  $old
 */

use Noblogs\Core\Csrf;
use Noblogs\Core\Url;

$this->layout('layouts/dashboard');
?>
<header class="dash-head">
  <h1><?= e(__('blog.create.title')) ?></h1>
</header>

<?php if (!$canCreate): ?>
  <div class="dash-empty">
    <p>
      <?php if (!$verified): ?>
        <?= e(__('dashboard.email_not_verified')) ?>
      <?php else: ?>
        <?= e(__('blog.create.limit_reached', ['max' => $maxBlogs])) ?>
      <?php endif; ?>
    </p>
    <p><a class="button" href="<?= e(Url::to('/dashboard')) ?>"><?= e(__('dashboard.back')) ?></a></p>
  </div>
<?php else: ?>
  <form method="post" action="<?= e(Url::to('/dashboard/nuovo-blog')) ?>" class="panel form">
    <?= Csrf::field() ?>

    <div class="field">
      <label for="title"><?= e(__('blog.create.field_title')) ?></label>
      <input type="text" id="title" name="title" maxlength="200" required
             value="<?= e($old['title'] ?? '') ?>"
             placeholder="<?= e(__('blog.create.title_placeholder')) ?>">
    </div>

    <div class="field">
      <label for="subdomain"><?= e(__('blog.create.field_subdomain')) ?></label>
      <div class="input-suffix">
        <input type="text" id="subdomain" name="subdomain" maxlength="63" required
               pattern="[a-z0-9]([a-z0-9-]*[a-z0-9])?"
               value="<?= e($old['subdomain'] ?? '') ?>"
               autocapitalize="none" autocomplete="off" spellcheck="false">
        <span class="suffix">.<?= e($domain) ?></span>
      </div>
      <p class="hint"><?= e(__('blog.create.subdomain_hint')) ?></p>
    </div>

    <p class="hint"><?= e(__('blog.create.quota', ['used' => $blogCount, 'max' => $maxBlogs])) ?></p>

    <div class="actions">
      <button type="submit" class="primary"><?= e(__('blog.create.submit')) ?></button>
      <a href="<?= e(Url::to('/dashboard')) ?>"><?= e(__('form.cancel')) ?></a>
    </div>
  </form>
<?php endif; ?>
