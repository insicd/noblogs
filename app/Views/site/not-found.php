<?php
/**
 * @var \Noblogs\Core\View   $this
 * @var \Noblogs\Models\Blog $blog
 */

use Noblogs\Core\Url;

$message = $message ?? '';

$this->layout('layouts/site');
$this->start('content');
?>
<h1 class="archive-heading"><?= e(__('site.not_found_title')) ?></h1>

<p><?= e($message !== '' ? $message : __('site.not_found_body')) ?></p>

<p>
  <a href="<?= e(Url::site('/')) ?>"><?= e(__('site.back_home')) ?></a> ·
  <a href="<?= e(Url::site('/' . trim($blog->blog_path, '/') . '/')) ?>"><?= e(__('blog.all_posts')) ?></a> ·
  <a href="<?= e(Url::site('/cerca/')) ?>"><?= e(__('search.title')) ?></a>
</p>
<?php
$this->end();
