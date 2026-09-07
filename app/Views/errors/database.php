<?php
/**
 * Database non raggiungibile.
 *
 * È la pagina che deve funzionare quando non funziona niente: viene resa dal
 * front controller prima che esistano tenant, utente, traduzioni e variabili
 * condivise della vista. Quindi niente layout, niente I18n, niente accesso al
 * database, nessuna funzione dell'applicazione — nemmeno e() — e stile in
 * linea. Il dettaglio dell'errore compare solo con il debug attivo.
 *
 * @var bool|null            $debug
 * @var \Throwable|null      $error
 */

declare(strict_types=1);

$showDetails = ($debug ?? false) === true && isset($error) && $error instanceof \Throwable;
$escape = static fn(string $value): string
    => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

?><!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Servizio momentaneamente non disponibile</title>
<style>
  :root { color-scheme: light dark; }
  body {
    margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 2rem;
    font: 1rem/1.6 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    color: #1a1a1a; background: #fbfbf9;
  }
  main { max-width: 40rem; }
  h1 { font-size: 1.5rem; line-height: 1.25; margin: 0 0 1rem; }
  p { margin: 0 0 1rem; }
  .code { font-size: 3rem; font-weight: 700; color: #9a9a92; margin: 0 0 .5rem; }
  pre {
    overflow: auto; padding: 1rem; border-radius: .25rem;
    background: #f1f1ec; font: .8125rem/1.5 ui-monospace, SFMono-Regular, Menlo, monospace;
  }
  .detail { border-top: 1px solid #dededa; margin-top: 2rem; padding-top: 1rem; }
  .detail h2 { font-size: 1rem; margin: 0 0 .5rem; }
  @media (prefers-color-scheme: dark) {
    body { color: #e8e8e4; background: #16161a; }
    .code { color: #5c5c66; }
    pre { background: #202027; }
    .detail { border-top-color: #35353d; }
  }
</style>
</head>
<body>
<main>
  <p class="code">503</p>
  <h1>Servizio momentaneamente non disponibile</h1>
  <p>
    Non riusciamo a raggiungere la banca dati del sito, quindi in questo momento non possiamo
    mostrarti nessuna pagina. Non è colpa tua e non hai perso nulla di quello che avevi scritto.
  </p>
  <p>Riprova tra un paio di minuti.</p>

<?php if ($showDetails): ?>
  <div class="detail">
    <h2>Dettaglio (visibile perché il debug è attivo)</h2>
    <p><strong><?= $escape($error::class) ?></strong>: <?= $escape($error->getMessage()) ?></p>
    <pre><?= $escape($error->getFile() . ':' . $error->getLine() . "\n\n" . $error->getTraceAsString()) ?></pre>
  </div>
<?php endif; ?>
</main>
</body>
</html>
