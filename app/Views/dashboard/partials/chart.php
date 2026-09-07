<?php
/**
 * Grafico giornaliero delle letture, in SVG generato dal server.
 *
 * Niente librerie e niente JavaScript: i dati sono già qui, e disegnarli è
 * questione di aritmetica. Fino a due mesi si usano le barre, perché ogni
 * giorno resta distinguibile; oltre, un'area continua è più leggibile di
 * trecento stanghette da un pixel.
 *
 * @var list<array{date:string,reads:int,visitors:int}> $series
 * @var \Noblogs\Models\User $user
 */

use Noblogs\Support\Dates;

$count = count($series);
if ($count === 0) {
    return;
}

$width = 760;
$height = 240;
$padLeft = 46;
$padRight = 12;
$padTop = 14;
$padBottom = 30;
$plotWidth = $width - $padLeft - $padRight;
$plotHeight = $height - $padTop - $padBottom;

$peak = 0;
$totalReads = 0;
foreach ($series as $point) {
    $peak = max($peak, $point['reads'], $point['visitors']);
    $totalReads += $point['reads'];
}

// Tetto arrotondato all'ordine di grandezza: le linee guida cadono su numeri
// che si leggono senza sforzo.
$ceiling = 4;
if ($peak > 0) {
    $magnitude = 10 ** max(0, (int) floor(log10((float) $peak)));
    $ceiling = (int) (ceil($peak / $magnitude) * $magnitude);
    $ceiling = max(4, (int) (ceil($ceiling / 4) * 4));
}

$band = $plotWidth / $count;
$scale = static fn(int $value): float => $padTop + $plotHeight - ($value / $ceiling) * $plotHeight;
$centre = static fn(int $index): float => $padLeft + $band * ($index + 0.5);

$useBars = $count <= 60;
$barWidth = max(1.0, $band - ($count <= 14 ? 12 : ($count <= 31 ? 4 : 1.5)));

$areaPoints = [];
$linePoints = [];
foreach ($series as $index => $point) {
    $areaPoints[] = round($centre($index), 1) . ',' . round($scale($point['reads']), 1);
    $linePoints[] = round($centre($index), 1) . ',' . round($scale($point['visitors']), 1);
}

$labelEvery = (int) max(1, ceil($count / 7));
$summary = __('analytics.chart_summary', [
    'days'  => $count,
    'reads' => $totalReads,
    'peak'  => $peak,
]);
?>
<figure class="chart">
  <svg viewBox="0 0 <?= $width ?> <?= $height ?>" class="chart-svg"
       role="img" aria-label="<?= e($summary) ?>" preserveAspectRatio="none">

    <?php for ($line = 0; $line <= 4; $line++): ?>
      <?php
        $value = (int) round($ceiling * $line / 4);
        $y = round($scale($value), 1);
      ?>
      <line class="chart-grid" x1="<?= $padLeft ?>" y1="<?= $y ?>" x2="<?= $width - $padRight ?>" y2="<?= $y ?>"></line>
      <text class="chart-axis" x="<?= $padLeft - 8 ?>" y="<?= $y + 4 ?>" text-anchor="end"><?= $value ?></text>
    <?php endfor; ?>

    <?php if ($useBars): ?>
      <?php foreach ($series as $index => $point): ?>
        <?php
          $top = round($scale($point['reads']), 1);
          $barHeight = round($padTop + $plotHeight - $top, 1);
          $x = round($centre($index) - $barWidth / 2, 1);
        ?>
        <?php if ($point['reads'] > 0): ?>
          <rect class="chart-bar" x="<?= $x ?>" y="<?= $top ?>"
                width="<?= round($barWidth, 1) ?>" height="<?= max(1, $barHeight) ?>"></rect>
        <?php endif; ?>
      <?php endforeach; ?>
    <?php else: ?>
      <polygon class="chart-area"
               points="<?= e($padLeft . ',' . ($padTop + $plotHeight) . ' ' . implode(' ', $areaPoints) . ' ' . ($width - $padRight) . ',' . ($padTop + $plotHeight)) ?>"></polygon>
      <polyline class="chart-line reads" points="<?= e(implode(' ', $areaPoints)) ?>"></polyline>
    <?php endif; ?>

    <polyline class="chart-line visitors" points="<?= e(implode(' ', $linePoints)) ?>"></polyline>

    <line class="chart-axis-line" x1="<?= $padLeft ?>" y1="<?= $padTop + $plotHeight ?>"
          x2="<?= $width - $padRight ?>" y2="<?= $padTop + $plotHeight ?>"></line>

    <?php foreach ($series as $index => $point): ?>
      <?php if ($index % $labelEvery !== 0 && $index !== $count - 1) {
          continue;
      } ?>
      <?php $date = Dates::parse($point['date']); ?>
      <?php if ($date !== null): ?>
        <text class="chart-axis" x="<?= round($centre($index), 1) ?>" y="<?= $height - 10 ?>" text-anchor="middle">
          <?= e(Dates::format($date, $count > 120 ? 'M' : 'j/n', $user->locale)) ?>
        </text>
      <?php endif; ?>
    <?php endforeach; ?>
  </svg>

  <figcaption class="chart-legend">
    <span class="key reads"></span> <?= e(__('analytics.reads')) ?>
    <span class="key visitors"></span> <?= e(__('analytics.visitors')) ?>
  </figcaption>
</figure>
