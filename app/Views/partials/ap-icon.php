<?php
/**
 * Marchio ActivityPub, in SVG inline: nessun file da scaricare e il colore
 * segue currentColor del contesto.
 *
 * @var string $class
 */
$class = trim((string) ($class ?? 'ap-icon'));
?>
<svg class="<?= e($class) ?>" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="1em" height="1em" fill="currentColor" aria-hidden="true" focusable="false">
  <path d="M10.71 9.284 14.38 8.07l1.254 2.17-1.764 3.018 2.121 1.235-.63 3.551-4.872-.409 1.02-2.5-2.322.635-3.01-5.207zm.564-6.415L7.633 6.208 4.32 8.12.997 12.9l3.71 6.405 9.428 5.45 3.72-6.443-2.52-4.35 1.764-3.014-3.325-1.91-2.52 4.347-1.487-.86 4.19-7.656zM12.18.245 7.69 7.997l4.426 2.539L8.894 16.53l3.316 1.91 3.21-5.555 3.316 1.906L23.004 8.12 12.181.245z"/>
</svg>
