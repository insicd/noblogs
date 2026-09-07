<?php
/**
 * @var \Noblogs\Core\View $this
 * @var string             $heading
 * @var string             $message
 */

use Noblogs\Core\Url;

$this->layout('layouts/dashboard');
?>
<div class="dash-empty">
  <h1><?= e($heading) ?></h1>
  <p><?= e($message) ?></p>
  <p><a class="button" href="<?= e(Url::to('/dashboard')) ?>"><?= e(__('dashboard.back')) ?></a></p>
</div>
