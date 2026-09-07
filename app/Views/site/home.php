<?php
/** @var \Noblogs\Core\View $this */
/** @var string $contentHtml */

$this->layout('layouts/site');
$this->start('content');
?>
<div class="post-content"><?= $contentHtml ?></div>
<?php
$this->end();
