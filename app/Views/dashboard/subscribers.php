<?php
/**
 * Iscritti agli aggiornamenti.
 *
 * @var \Noblogs\Core\View   $this
 * @var \Noblogs\Models\Blog $blog
 * @var \Noblogs\Models\User $user
 * @var list<\Noblogs\Models\Subscriber> $subscribers
 * @var int                  $confirmed
 * @var int                  $pending
 */

use Noblogs\Core\Csrf;
use Noblogs\Core\Url;
use Noblogs\Support\Dates;

$base = '/dashboard/' . $blog->subdomain;

$this->layout('layouts/dashboard');
?>
<header class="dash-head">
  <h1><?= e(__('subscribers.title')) ?></h1>
  <p class="dash-head-note"><?= e(__('subscribers.intro')) ?></p>
</header>

<?php if (!$blog->subscriptions_active): ?>
  <div class="notice">
    <p><?= e(__('subscribers.disabled')) ?></p>
    <p><a class="button" href="<?= e(Url::to($base . '/impostazioni')) ?>"><?= e(__('subscribers.enable')) ?></a></p>
  </div>
<?php endif; ?>

<dl class="stat-grid">
  <div>
    <dt><?= e(__('subscribers.confirmed')) ?></dt>
    <dd><?= e((string) $confirmed) ?></dd>
  </div>
  <div>
    <dt><?= e(__('subscribers.pending')) ?></dt>
    <dd><?= e((string) max(0, $pending)) ?></dd>
  </div>
</dl>

<section class="notice">
  <h2><?= e(__('subscribers.howto_title')) ?></h2>
  <p><?= e(__('subscribers.howto_body')) ?></p>
  <ul>
    <li><?= e(__('subscribers.howto_1')) ?></li>
    <li><?= e(__('subscribers.howto_2')) ?></li>
    <li><?= e(__('subscribers.howto_3')) ?></li>
  </ul>
  <p><a class="button" href="<?= e(Url::to($base . '/iscritti') . '?esporta=csv') ?>"><?= e(__('subscribers.export')) ?></a></p>
</section>

<?php if ($subscribers === []): ?>
  <div class="dash-empty">
    <p><?= e(__('subscribers.empty')) ?></p>
  </div>
<?php else: ?>
  <table class="data-table">
    <thead>
      <tr>
        <th scope="col"><?= e(__('subscribers.column_email')) ?></th>
        <th scope="col"><?= e(__('subscribers.column_since')) ?></th>
        <th scope="col"><?= e(__('post.column.actions')) ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($subscribers as $subscriber): ?>
        <?php $since = Dates::parse($subscriber->confirmed_at ?? $subscriber->created_at); ?>
        <tr>
          <td class="mono"><?= e($subscriber->email) ?></td>
          <td>
            <?php if ($since !== null): ?>
              <time datetime="<?= e($since->format('c')) ?>">
                <?= e(Dates::format(Dates::toLocal($since, $user->timezone), 'j M Y', $user->locale)) ?>
              </time>
            <?php endif; ?>
          </td>
          <td class="cell-actions">
            <details class="danger-details">
              <summary><?= e(__('subscribers.remove')) ?></summary>
              <form method="post" action="<?= e(Url::to($base . '/iscritti')) ?>">
                <?= Csrf::field() ?>
                <input type="hidden" name="id" value="<?= e((string) $subscriber->id) ?>">
                <p><?= e(__('subscribers.remove_confirm', ['email' => $subscriber->email])) ?></p>
                <button type="submit" class="danger"><?= e(__('subscribers.remove')) ?></button>
              </form>
            </details>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
