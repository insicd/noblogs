<?php
/**
 * Impostazioni della piattaforma.
 *
 * @var \Noblogs\Core\View  $this
 * @var array<string,mixed> $values
 */

use Noblogs\Core\Csrf;
use Noblogs\Core\Session;
use Noblogs\Core\Url;

$this->layout('layouts/admin');

// Dopo un salvataggio rifiutato si ripropone quello che era stato scritto,
// non quello che c'è nel database.
$old = Session::takeOldInput();
$value = static function (string $key, mixed $fallback) use ($old): string {
    return (string) ($old[$key] ?? $fallback);
};
$checked = static function (string $key, bool $fallback) use ($old): bool {
    return $old === [] ? $fallback : isset($old[$key]);
};

$this->start('title');
echo __('admin.settings.title');
$this->end();

$this->start('intro');
echo __('admin.settings.intro');
$this->end();

?>

<form method="post" action="<?= e(Url::to('/admin/impostazioni')) ?>" class="admin-form">
  <?= Csrf::field() ?>

  <div class="admin-card">
    <h2><?= e(__('admin.settings.section.identity')) ?></h2>

    <div class="admin-field">
      <label for="site_name"><?= e(__('admin.settings.site_name')) ?></label>
      <input type="text" id="site_name" name="site_name" maxlength="100" required
             value="<?= e($value('site_name', $values['site_name'])) ?>">
    </div>

    <div class="admin-field">
      <label for="tagline"><?= e(__('admin.settings.tagline')) ?></label>
      <input type="text" id="tagline" name="tagline" maxlength="200"
             value="<?= e($value('tagline', $values['tagline'])) ?>">
      <p class="admin-hint"><?= e(__('admin.settings.tagline_hint')) ?></p>
    </div>

    <div class="admin-field">
      <label for="contact_email"><?= e(__('admin.settings.contact_email')) ?></label>
      <input type="email" id="contact_email" name="contact_email" maxlength="191"
             value="<?= e($value('contact_email', $values['contact_email'])) ?>">
      <p class="admin-hint"><?= e(__('admin.settings.contact_email_hint')) ?></p>
    </div>
  </div>

  <div class="admin-card">
    <h2><?= e(__('admin.settings.section.access')) ?></h2>

    <div class="admin-check">
      <input type="checkbox" id="registration_open" name="registration_open" value="1"
             <?= $checked('registration_open', (bool) $values['registration_open']) ? 'checked' : '' ?>>
      <div>
        <label for="registration_open"><?= e(__('admin.settings.registration_open')) ?></label>
        <p class="admin-hint"><?= e(__('admin.settings.registration_open_hint')) ?></p>
      </div>
    </div>

    <div class="admin-check">
      <input type="checkbox" id="verify_email" name="verify_email" value="1"
             <?= $checked('verify_email', (bool) $values['verify_email']) ? 'checked' : '' ?>>
      <div>
        <label for="verify_email"><?= e(__('admin.settings.verify_email')) ?></label>
        <p class="admin-hint"><?= e(__('admin.settings.verify_email_hint')) ?></p>
      </div>
    </div>

    <div class="admin-check">
      <input type="checkbox" id="review_blogs" name="review_blogs" value="1"
             <?= $checked('review_blogs', (bool) $values['review_blogs']) ? 'checked' : '' ?>>
      <div>
        <label for="review_blogs"><?= e(__('admin.settings.review_blogs')) ?></label>
        <p class="admin-hint"><?= e(__('admin.settings.review_blogs_hint')) ?></p>
      </div>
    </div>
  </div>

  <div class="admin-card">
    <h2><?= e(__('admin.settings.section.limits')) ?></h2>

    <div class="admin-field">
      <label for="blogs_per_user"><?= e(__('admin.settings.blogs_per_user')) ?></label>
      <input type="number" id="blogs_per_user" name="blogs_per_user" min="0" max="1000"
             value="<?= e($value('blogs_per_user', (string) $values['blogs_per_user'])) ?>">
      <p class="admin-hint"><?= e(__('admin.settings.blogs_per_user_hint')) ?></p>
    </div>

    <div class="admin-field">
      <label for="posts_per_blog"><?= e(__('admin.settings.posts_per_blog')) ?></label>
      <input type="number" id="posts_per_blog" name="posts_per_blog" min="1" max="1000000"
             value="<?= e($value('posts_per_blog', (string) $values['posts_per_blog'])) ?>">
    </div>

    <div class="admin-field">
      <label for="storage_per_blog"><?= e(__('admin.settings.storage_per_blog')) ?></label>
      <input type="number" id="storage_per_blog" name="storage_per_blog" min="1" max="1048576"
             value="<?= e($value('storage_per_blog', (string) $values['storage_per_blog'])) ?>">
    </div>

    <div class="admin-field">
      <label for="upload_max"><?= e(__('admin.settings.upload_max')) ?></label>
      <input type="number" id="upload_max" name="upload_max" min="1" max="1024"
             value="<?= e($value('upload_max', (string) $values['upload_max'])) ?>">
      <p class="admin-hint"><?= e(__('admin.settings.upload_max_hint')) ?></p>
    </div>
  </div>

  <div class="admin-card">
    <h2><?= e(__('admin.settings.section.notice')) ?></h2>

    <div class="admin-field">
      <label for="notice"><?= e(__('admin.settings.notice')) ?></label>
      <textarea id="notice" name="notice" maxlength="2000" rows="4"><?= e($value('notice', $values['notice'])) ?></textarea>
      <p class="admin-hint"><?= e(__('admin.settings.notice_hint')) ?></p>
    </div>
  </div>

  <button type="submit" class="admin-btn admin-btn--primary"><?= e(__('form.save')) ?></button>
</form>

<p class="admin-hint"><?= e(__('admin.settings.file_note')) ?></p>
