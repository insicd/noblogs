<?php
/**
 * Pagina di ingresso: cos'è Noblogs, come si comincia, cosa c'è da leggere.
 *
 * @var \Noblogs\Core\View $this
 * @var bool $loggedIn
 * @var array{blogs:int,posts:int} $stats
 * @var list<array{post:\Noblogs\Models\Post,blog:\Noblogs\Models\Blog}> $entries
 * @var string $domain
 * @var string $tagline
 * @var array<string,mixed> $old
 */

use Noblogs\Core\Config;
use Noblogs\Core\Url;

$loggedIn = (bool) ($loggedIn ?? false);
$siteName = (string) ($siteName ?? Config::get('site.name', 'Noblogs'));
$number = static fn(int $value): string => number_format($value, 0, ',', '.');

$features = [
    'privacy'  => '>',
    'speed'    => '>',
    'markdown' => '>',
    'yours'    => '>',
];

$this->layout('layouts/platform');
$this->start('content');
?>
<section class="pf-hero pf-shell">
  <div class="pf-hero-text">
    <h1><?= e(__('platform.home.heading')) ?></h1>
    <p class="pf-lead"><?= e(__('platform.home.lead')) ?></p>
  </div>

  <div class="pf-hero-form">
    <?php if ($loggedIn): ?>
      <h2><?= e(__('platform.home.dashboard_heading')) ?></h2>
      <p>
        <a class="pf-button pf-button-big" href="<?= e(Url::to('/dashboard')) ?>"><?= e(__('platform.home.dashboard_link')) ?></a>
      </p>
    <?php else: ?>
      <h2><?= e(__('platform.home.form_heading')) ?></h2>

      <?php /* Modulo rapido: porta a /registrati con l'indirizzo già scritto.
               È una GET perché non cambia nulla; email e password si inseriscono
               nella pagina successiva, non in una query string. */ ?>
      <form method="get" action="<?= e(Url::to('/registrati')) ?>">
        <label for="nb-quick-subdomain"><?= e(__('auth.register.subdomain')) ?></label>
        <div class="pf-field-inline">
          <input type="text" id="nb-quick-subdomain" name="subdomain"
                 value="<?= e((string) ($old['subdomain'] ?? '')) ?>"
                 placeholder="ilmioblog" inputmode="url" autocapitalize="none"
                 spellcheck="false" maxlength="63" pattern="[a-z0-9-]+">
          <span class="pf-field-suffix">.<?= e($domain) ?></span>
        </div>
        <button type="submit" class="pf-button pf-button-big"><?= e(__('platform.home.form_submit')) ?></button>
        <p class="pf-form-note"><?= e(__('platform.home.form_note')) ?></p>
      </form>
    <?php endif; ?>
  </div>
</section>

<section class="pf-features pf-shell">
  <h2><?= e(__('platform.home.features_heading')) ?></h2>
  <ul class="pf-feature-grid">
    <?php foreach ($features as $key => $glyph): ?>
      <li>
        <h3><span class="pf-feature-glyph" aria-hidden="true"><?= e($glyph) ?></span>
          <?= e(__('platform.home.feature.' . $key . '.title')) ?></h3>
        <p><?= e(__('platform.home.feature.' . $key . '.body')) ?></p>
      </li>
    <?php endforeach; ?>
  </ul>
</section>

<?php if ($entries !== []): ?>
<section class="pf-recent pf-shell">
  <h2><?= e(__('platform.home.recent_heading')) ?></h2>
  <?php $this->partial('platform/partials/entries', ['entries' => $entries, 'showDescription' => true]); ?>
  <p class="pf-more">
    <a href="<?= e(Url::to('/esplora')) ?>"><?= e(__('platform.home.recent_more')) ?> →</a>
  </p>
</section>
<?php endif; ?>

<?php if ($tagline !== ''): ?>
<p class="pf-shell pf-tagline"><?= e($siteName) ?> — <?= e($tagline) ?></p>
<?php endif; ?>
<?php
$this->end();
echo $this->slot('content'); // vedi la nota sullo slot in layouts/platform.php
