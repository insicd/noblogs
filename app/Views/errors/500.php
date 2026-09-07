<?php
/**
 * Errore interno.
 *
 * Questa pagina viene mostrata quando qualcosa è andato storto, e quel
 * qualcosa può essere il database, la configurazione o le traduzioni. Perciò
 * non usa il layout, non interroga il database, non chiama I18n e non dipende
 * da nessuna funzione dell'applicazione: HTML e stile in linea, testo fisso in
 * italiano. Meno cose può rompere, più è probabile che si veda.
 */

declare(strict_types=1);

?><!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Errore del server</title>
<style>
  :root { color-scheme: light dark; }
  body {
    margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 2rem;
    font: 1rem/1.6 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    color: #1a1a1a; background: #fbfbf9;
  }
  main { max-width: 34rem; }
  h1 { font-size: 1.5rem; line-height: 1.25; margin: 0 0 1rem; }
  p { margin: 0 0 1rem; }
  .code { font-size: 3rem; font-weight: 700; color: #9a9a92; margin: 0 0 .5rem; }
  a { color: #14507d; }
  @media (prefers-color-scheme: dark) {
    body { color: #e8e8e4; background: #16161a; }
    .code { color: #5c5c66; }
    a { color: #8ec5f0; }
  }
</style>
</head>
<body>
<main>
  <p class="code">500</p>
  <h1>Qualcosa è andato storto</h1>
  <p>
    Il server ha incontrato un errore imprevisto mentre preparava questa pagina. L'errore è stato
    registrato e non dipende da quello che hai fatto tu.
  </p>
  <p>Riprova tra qualche minuto: quasi sempre è un problema momentaneo.</p>
  <p><a href="/">Torna alla pagina iniziale</a></p>
</main>
</body>
</html>
