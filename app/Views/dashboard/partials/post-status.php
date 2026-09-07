<?php
/**
 * Pastiglia con lo stato di un articolo.
 *
 * @var \Noblogs\Models\Post $post
 */

if ($post->isDraft()) {
    $class = 'draft';
    $label = __('post.status.draft');
} elseif ($post->isScheduled()) {
    $class = 'scheduled';
    $label = __('post.status.scheduled');
} elseif ($post->hidden) {
    $class = 'hidden';
    $label = __('post.status.hidden');
} else {
    $class = 'live';
    $label = __('post.status.published');
}
?>
<span class="pill <?= e($class) ?>"><?= e($label) ?></span>
