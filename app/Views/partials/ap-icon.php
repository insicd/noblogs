<?php
/**
 * Marchio ActivityPub usato in homepage e nel piè di pagina dei blog.
 *
 * @var string $class
 */

use Noblogs\Core\Url;

$class = trim((string) ($class ?? 'ap-icon'));
?>
<img class="<?= e($class) ?>" src="<?= e(Url::asset('img/activitypub.png')) ?>" alt="" width="32" height="32" decoding="async">
