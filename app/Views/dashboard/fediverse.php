<?php
/**
 * Commenti dal fediverso e risposte dell'autore.
 *
 * @var \Noblogs\Core\View $this
 * @var \Noblogs\Models\Blog $blog
 * @var string $handle
 * @var bool $enabled
 * @var bool $instanceOn
 * @var list<\Noblogs\Models\ApNotification> $comments
 * @var array<int,list<\Noblogs\Models\ApNotification>> $replies
 * @var array<string,mixed> $old
 */

use Noblogs\Core\Csrf;
use Noblogs\Core\Url;
use Noblogs\Support\Dates;

$base = '/dashboard/' . $blog->subdomain;
$user = $user ?? null;
$locale = $user !== null ? $user->locale : 'it';
$old = is_array($old ?? null) ? $old : [];
$openId = (int) ($old['reply_to'] ?? 0);
$oldBody = (string) ($old['body'] ?? '');

$this->layout('layouts/dashboard');
?>
<header class="dash-head">
  <h1><?= e(__('federation.inbox.title')) ?></h1>
  <p class="dash-head-note"><?= e(__('federation.inbox.intro', ['handle' => '@' . $handle])) ?></p>
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

<?php if ($comments === []): ?>
  <div class="dash-empty">
    <p><?= e(__('federation.inbox.empty')) ?></p>
  </div>
<?php else: ?>
  <ul class="fed-list">
    <?php foreach ($comments as $item): ?>
      <?php
        $post = $item->post();
        $thread = $replies[$item->id] ?? [];
        $canReply = $enabled && is_string($item->object_id) && $item->object_id !== '';
        $isOpen = $openId === $item->id;
      ?>
      <li class="fed-item" id="commento-<?= e((string) $item->id) ?>">
        <?php if ($item->avatar_url): ?>
          <img class="fed-avatar" src="<?= e($item->avatar_url) ?>" alt="" width="40" height="40" loading="lazy">
        <?php else: ?>
          <span class="fed-avatar fed-avatar--empty" aria-hidden="true"></span>
        <?php endif; ?>
        <div>
          <p class="fed-summary">
            <strong><?= e($item->actorLabel()) ?></strong>
            <?= e(__('federation.inbox.commented', [
                'title' => $post !== null ? $post->title : __('federation.a_post'),
            ])) ?>
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
          </div>

          <?php if ($thread !== []): ?>
            <ul class="fed-thread">
              <?php foreach ($thread as $reply): ?>
                <li class="fed-item fed-item--reply">
                  <span class="fed-avatar fed-avatar--you" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($blog->title, 0, 1))) ?></span>
                  <div>
                    <p class="fed-summary"><strong><?= e(__('federation.reply.you')) ?></strong></p>
                    <blockquote class="fed-quote"><?= e((string) $reply->content) ?></blockquote>
                    <div class="fed-meta">
                      <time datetime="<?= e($reply->created_at) ?>"><?= e(Dates::since(Dates::parse($reply->created_at), $locale)) ?></time>
                    </div>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>

          <?php if ($canReply): ?>
            <details class="fed-reply" <?= $isOpen ? 'open' : '' ?>>
              <summary><?= e(__('federation.reply.open')) ?></summary>
              <form method="post" action="<?= e(Url::to($base . '/fediverso/' . $item->id . '/rispondi')) ?>">
                <?= Csrf::field() ?>
                <label class="visually-hidden" for="reply-<?= e((string) $item->id) ?>"><?= e(__('federation.reply.label')) ?></label>
                <textarea id="reply-<?= e((string) $item->id) ?>" name="body" rows="4" required
                          maxlength="5000"><?= e($isOpen ? $oldBody : '') ?></textarea>
                <p class="hint"><?= e(__('federation.reply.hint')) ?></p>
                <p>
                  <button type="submit" class="button primary"><?= e(__('federation.reply.submit')) ?></button>
                </p>
              </form>
            </details>
          <?php elseif ($enabled): ?>
            <p class="hint"><?= e(__('federation.reply.unavailable')) ?></p>
          <?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
