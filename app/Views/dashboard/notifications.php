<?php
/**
 * Notifiche dal fediverso: follower e commenti.
 *
 * @var \Noblogs\Core\View $this
 * @var \Noblogs\Models\Blog $blog
 * @var string $tab
 * @var string $handle
 * @var bool $enabled
 * @var bool $instanceOn
 * @var list<\Noblogs\Models\ApFollower> $followers
 * @var int $followerCount
 * @var list<\Noblogs\Models\ApNotification> $notifications
 * @var int $unread
 */

use Noblogs\Core\Csrf;
use Noblogs\Core\Url;
use Noblogs\Support\Dates;

$base = '/dashboard/' . $blog->subdomain;
$user = $user ?? null;
$locale = $user !== null ? $user->locale : 'it';

$this->layout('layouts/dashboard');
?>
<header class="dash-head">
  <h1><?= e(__('federation.notifications.title')) ?></h1>
  <p class="dash-head-note"><?= e(__('federation.notifications.intro', ['handle' => '@' . $handle])) ?></p>
</header>

<?php if (!$instanceOn): ?>
  <p class="notice"><?= e(__('federation.notifications.instance_off')) ?></p>
<?php elseif (!$blog->federation_active): ?>
  <p class="notice"><?= e(__('federation.notifications.blog_off')) ?>
    <a href="<?= e(Url::to($base . '/impostazioni')) ?>"><?= e(__('federation.notifications.enable')) ?></a>
  </p>
<?php elseif (!$enabled): ?>
  <p class="notice"><?= e(__('federation.notifications.pending')) ?></p>
<?php endif; ?>

<dl class="stat-grid">
  <div>
    <dt><?= e(__('federation.stat.followers')) ?></dt>
    <dd><?= e((string) $followerCount) ?></dd>
  </div>
  <div>
    <dt><?= e(__('federation.stat.unread')) ?></dt>
    <dd><?= e((string) $unread) ?></dd>
  </div>
</dl>

<p class="dash-tabs">
  <a href="<?= e(Url::to($base . '/notifiche?vista=attivita')) ?>"
     class="<?= $tab === 'attivita' ? 'current' : '' ?>"><?= e(__('federation.tab.activity')) ?></a>
  <a href="<?= e(Url::to($base . '/notifiche?vista=follower')) ?>"
     class="<?= $tab === 'follower' ? 'current' : '' ?>"><?= e(__('federation.tab.followers')) ?></a>
</p>

<?php if ($tab === 'follower'): ?>
  <?php if ($followers === []): ?>
    <div class="dash-empty">
      <p><?= e(__('federation.followers.empty')) ?></p>
    </div>
  <?php else: ?>
    <ul class="fed-list">
      <?php foreach ($followers as $follower): ?>
        <li class="fed-item">
          <?php if ($follower->avatar_url): ?>
            <img class="fed-avatar" src="<?= e($follower->avatar_url) ?>" alt="" width="40" height="40" loading="lazy">
          <?php else: ?>
            <span class="fed-avatar fed-avatar--empty" aria-hidden="true"></span>
          <?php endif; ?>
          <div>
            <strong><?= e($follower->display_name !== null && $follower->display_name !== ''
                ? $follower->display_name
                : ($follower->handle ?? $follower->actor_id)) ?></strong>
            <?php if ($follower->handle): ?>
              <div class="fed-handle">@<?= e($follower->handle) ?></div>
            <?php endif; ?>
            <div class="fed-meta">
              <time datetime="<?= e($follower->created_at) ?>"><?= e(Dates::since(Dates::parse($follower->created_at), $locale)) ?></time>
              · <a href="<?= e($follower->actor_id) ?>" rel="noopener noreferrer"><?= e(__('federation.open_profile')) ?></a>
            </div>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
<?php else: ?>
  <?php if ($unread > 0): ?>
    <form method="post" action="<?= e(Url::to($base . '/notifiche/lette')) ?>" class="fed-mark">
      <?= Csrf::field() ?>
      <button type="submit" class="button"><?= e(__('federation.notifications.mark_all')) ?></button>
    </form>
  <?php endif; ?>

  <?php if ($notifications === []): ?>
    <div class="dash-empty">
      <p><?= e(__('federation.activity.empty')) ?></p>
    </div>
  <?php else: ?>
    <ul class="fed-list">
      <?php foreach ($notifications as $item): ?>
        <?php $post = $item->post(); ?>
        <li class="fed-item<?= $item->is_read ? '' : ' is-unread' ?>">
          <?php if ($item->avatar_url): ?>
            <img class="fed-avatar" src="<?= e($item->avatar_url) ?>" alt="" width="40" height="40" loading="lazy">
          <?php else: ?>
            <span class="fed-avatar fed-avatar--empty" aria-hidden="true"></span>
          <?php endif; ?>
          <div>
            <p class="fed-summary">
              <?= e(match ($item->type) {
                  'follow'    => __('federation.event.follow', ['who' => $item->actorLabel()]),
                  'unfollow'  => __('federation.event.unfollow', ['who' => $item->actorLabel()]),
                  'comment'   => __('federation.event.comment', [
                      'who'   => $item->actorLabel(),
                      'title' => $post !== null ? $post->title : __('federation.a_post'),
                  ]),
                  'reply'     => __('federation.event.reply', [
                      'title' => $post !== null ? $post->title : __('federation.a_post'),
                  ]),
                  'like'      => __('federation.event.like', [
                      'who'   => $item->actorLabel(),
                      'title' => $post !== null ? $post->title : __('federation.a_post'),
                  ]),
                  'announce'  => __('federation.event.announce', [
                      'who'   => $item->actorLabel(),
                      'title' => $post !== null ? $post->title : __('federation.a_post'),
                  ]),
                  default     => $item->actorLabel(),
              }) ?>
            </p>
            <?php if ($item->content !== null && trim($item->content) !== ''): ?>
              <blockquote class="fed-quote"><?= e($item->content) ?></blockquote>
            <?php endif; ?>
            <div class="fed-meta">
              <?php if ($item->handle): ?>@<?= e($item->handle) ?> · <?php endif; ?>
              <time datetime="<?= e($item->created_at) ?>"><?= e(Dates::since(Dates::parse($item->created_at), $locale)) ?></time>
              <?php if ($post !== null): ?>
                · <a href="<?= e(Url::post($blog, $post->slug)) ?>" rel="noopener"><?= e($post->title) ?></a>
              <?php endif; ?>
              · <a href="<?= e($item->actor_id) ?>" rel="noopener noreferrer"><?= e(__('federation.open_profile')) ?></a>
              <?php if ($item->type === 'comment'): ?>
                · <a href="<?= e(Url::to($base . '/fediverso#commento-' . $item->id)) ?>"><?= e(__('federation.reply.open')) ?></a>
              <?php endif; ?>
            </div>
            <?php if (!$item->is_read): ?>
              <form method="post" action="<?= e(Url::to($base . '/notifiche/lette')) ?>" class="fed-mark-one">
                <?= Csrf::field() ?>
                <input type="hidden" name="id" value="<?= e((string) $item->id) ?>">
                <button type="submit" class="linkish"><?= e(__('federation.notifications.mark_one')) ?></button>
              </form>
            <?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
<?php endif; ?>
