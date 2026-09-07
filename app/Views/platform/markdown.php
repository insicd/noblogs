<?php
/**
 * Guida al Markdown: codice a sinistra, risultato a destra.
 *
 * Il risultato non è scritto a mano: arriva dal parser di Noblogs, lo stesso
 * che rende gli articoli. Se il parser cambia, cambia anche questa pagina.
 *
 * @var \Noblogs\Core\View $this
 * @var list<array{id:string,title:string,note:string,examples:list<array{source:string,html:string}>}> $sections
 * @var list<array{code:string,aliases:string,effect:string,block:bool}> $directives
 * @var list<array{code:string,effect:string}> $filters
 */

use Noblogs\Core\Url;

$blockDirectives = array_values(array_filter($directives, static fn(array $d): bool => $d['block']));
$inlineDirectives = array_values(array_filter($directives, static fn(array $d): bool => !$d['block']));

$this->layout('layouts/platform');
$this->start('content');
?>
<div class="pf-shell pf-prose pf-guide">
  <h1>Guida al Markdown</h1>

  <p class="pf-lead">
    Il Markdown è un modo di scrivere testo formattato usando solo caratteri comuni. Questa pagina
    mostra tutto quello che Noblogs riconosce: a sinistra come si scrive, a destra come viene
    mostrato. Gli esempi non sono descritti a parole, sono resi dallo stesso programma che rende i
    tuoi articoli — quindi quello che vedi è esattamente quello che otterrai.
  </p>

  <nav class="pf-toc" aria-label="<?= e(__('platform.markdown.contents')) ?>">
    <ol>
      <?php foreach ($sections as $section): ?>
        <li><a href="#<?= e($section['id']) ?>"><?= e($section['title']) ?></a></li>
      <?php endforeach; ?>
      <li><a href="#direttive">Direttive</a></li>
    </ol>
  </nav>

  <?php foreach ($sections as $section): ?>
    <section class="pf-md-section">
      <h2 id="<?= e($section['id']) ?>"><?= e($section['title']) ?></h2>
      <?php if ($section['note'] !== ''): ?>
        <p><?= e($section['note']) ?></p>
      <?php endif; ?>

      <?php foreach ($section['examples'] as $example): ?>
        <div class="pf-md-example">
          <div class="pf-md-source">
            <h3><?= e(__('platform.markdown.source')) ?></h3>
            <pre><code><?= e($example['source']) ?></code></pre>
          </div>
          <div class="pf-md-result">
            <h3><?= e(__('platform.markdown.result')) ?></h3>
            <?php /* HTML prodotto dal parser e già bonificato dal controller. */ ?>
            <div class="pf-md-rendered"><?= $example['html'] ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </section>
  <?php endforeach; ?>

  <section class="pf-md-section">
    <h2 id="direttive">Direttive</h2>
    <p>
      Le direttive sono segnaposto fra doppie graffe che Noblogs sostituisce quando genera la
      pagina. Servono a inserire elementi che cambiano nel tempo — l'elenco dei tuoi articoli, la
      nuvola dei tag, il modulo di iscrizione — senza dover scrivere HTML.
    </p>
    <p>
      Le direttive di <strong>blocco</strong> vanno scritte da sole nel loro paragrafo, con una
      riga vuota sopra e sotto: inseriscono un elenco o un modulo, che dentro un paragrafo non
      starebbe. Se le trovi in mezzo a una frase restano scritte come sono, così questa pagina può
      parlarne senza espanderle. Le direttive <strong>in linea</strong> inseriscono un valore e si
      possono usare dentro il testo.
    </p>

    <h3>Direttive di blocco</h3>
    <table class="pf-md-table">
      <thead>
        <tr>
          <th scope="col"><?= e(__('platform.markdown.directive_code')) ?></th>
          <th scope="col"><?= e(__('platform.markdown.directive_aliases')) ?></th>
          <th scope="col"><?= e(__('platform.markdown.directive_effect')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($blockDirectives as $directive): ?>
          <tr>
            <td><code><?= e($directive['code']) ?></code></td>
            <td><?= e($directive['aliases']) ?></td>
            <td><?= e($directive['effect']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <h3>Direttive in linea</h3>
    <table class="pf-md-table">
      <thead>
        <tr>
          <th scope="col"><?= e(__('platform.markdown.directive_code')) ?></th>
          <th scope="col"><?= e(__('platform.markdown.directive_aliases')) ?></th>
          <th scope="col"><?= e(__('platform.markdown.directive_effect')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($inlineDirectives as $directive): ?>
          <tr>
            <td><code><?= e($directive['code']) ?></code></td>
            <td><?= e($directive['aliases']) ?></td>
            <td><?= e($directive['effect']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <h3>Filtri</h3>
    <p>
      Alcune direttive accettano dei filtri, separati da una barra verticale e scritti come
      <code>nome:valore</code>. Si possono combinare:
      <code>{{ posts|limit:5|tag:appunti|description:sì }}</code>.
    </p>
    <table class="pf-md-table">
      <thead>
        <tr>
          <th scope="col">Filtro</th>
          <th scope="col">Cosa fa</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($filters as $filter): ?>
          <tr>
            <td><code><?= e($filter['code']) ?></code></td>
            <td><?= e($filter['effect']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <p>
      I nomi delle direttive e dei filtri esistono anche in italiano — <code>{{ elenco_post }}</code>,
      <code>{{ nuvola_tag }}</code>, <code>{{ indice }}</code>, <code>|limite:5</code>,
      <code>|ordine:asc</code> — e funzionano allo stesso modo. Una direttiva che non esiste resta
      scritta com'è nella pagina, senza errori: è il modo più semplice per accorgersi di un nome
      sbagliato.
    </p>
  </section>

  <p class="pf-form-links">
    <a href="<?= e(Url::to('/aiuto')) ?>">← Aiuto</a>
  </p>
</div>
<?php
$this->end();
echo $this->slot('content'); // vedi la nota sullo slot in layouts/platform.php
