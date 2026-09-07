<?php
/**
 * @var \Noblogs\Core\View   $this
 * @var \Noblogs\Models\Blog $blog
 */

use Noblogs\Core\Csrf;
use Noblogs\Core\Url;

$state = $state ?? null;
$error = $error ?? null;

$this->layout('layouts/site');
$this->start('content');
?>
<h1 class="archive-heading"><?= e(__('subscribe.title')) ?></h1>

<?php if ($state === 'pending'): ?>
  <p class="notice"><?= e(__('subscribe.check_inbox')) ?></p>

<?php elseif ($state === 'confirmed'): ?>
  <p class="notice"><?= e(__('subscribe.confirmed', ['blog' => $blog->title])) ?></p>
  <p><a href="<?= e(Url::site('/')) ?>"><?= e(__('subscribe.back_to_blog')) ?></a></p>

<?php elseif ($state === 'unsubscribed'): ?>
  <p class="notice"><?= e(__('subscribe.unsubscribed')) ?></p>
  <p><a href="<?= e(Url::site('/')) ?>"><?= e(__('subscribe.back_to_blog')) ?></a></p>

<?php else: ?>
  <p><?= e(__('subscribe.intro', ['blog' => $blog->title])) ?></p>

  <?php if ($error !== null): ?>
    <p class="error"><?= e($error) ?></p>
  <?php endif; ?>

  <form class="subscribe-form" method="post" action="<?= e(Url::site('/iscriviti/')) ?>">
    <label for="nb-subscribe-email"><?= e(__('subscribe.label')) ?></label>
    <input type="email" id="nb-subscribe-email" name="email" required
           placeholder="<?= e(__('subscribe.placeholder')) ?>" autocomplete="email">

    <div class="nb-hp" aria-hidden="true">
      <label for="nb-subscribe-website"><?= e(__('form.leave_empty')) ?></label>
      <input type="text" id="nb-subscribe-website" name="website" tabindex="-1" autocomplete="off">
    </div>

    <input type="hidden" name="ts" value="<?= e(Csrf::sign('subscribe', 7200)) ?>">
    <button type="submit"><?= e(__('subscribe.button')) ?></button>
  </form>

  <p class="subscribe-privacy"><?= e(__('subscribe.privacy_note')) ?></p>
<?php endif; ?>
<?php
$this->end();
