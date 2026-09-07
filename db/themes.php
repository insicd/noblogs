<?php

declare(strict_types=1);

/**
 * Galleria dei temi della piattaforma.
 *
 * Ogni tema espone gli stessi nomi di proprietà personalizzate in `:root`:
 * chi scrive CSS personalizzato può quindi ridefinirne una sola senza dover
 * riscrivere il foglio di stile. Il CSS finisce inline nella pagina, quindi
 * niente `@import` e nessuna risorsa esterna.
 *
 * L'array viene letto da Noblogs\Models\Theme::sync().
 */

return [

    'default' => [
        'title'       => 'Predefinito',
        'description' => 'Una colonna di testo leggibile, niente di più.',
        'sort_order'  => 0,
        'css'         => <<<'CSS'
/* ==========================================================================
   Predefinito — sobrio, font di sistema, scuro automatico.
   ========================================================================== */

:root {
  --width: 46rem;
  --font-main: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
  --font-mono: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, "Liberation Mono", monospace;
  --font-size: 1.0625rem;
  --line-height: 1.65;
  --color-bg: #ffffff;
  --color-text: #1a1a1a;
  --color-muted: #595959;
  --color-link: #0057b8;
  --color-border: #e3e3e6;
  --color-code-bg: #f4f4f6;
  --color-accent: #0057b8;
  --spacing: 1.5rem;
}

/* Stessi token, valori invertiti: il resto del foglio non cambia. */
@media (prefers-color-scheme: dark) {
  :root {
    --color-bg: #14171a;
    --color-text: #e6e6e6;
    --color-muted: #a0a6ad;
    --color-link: #7ab8ff;
    --color-border: #2c3238;
    --color-code-bg: #1d2126;
    --color-accent: #7ab8ff;
  }
}

/* --- Reset minimo -------------------------------------------------------- */
*, *::before, *::after { box-sizing: border-box; }
body, h1, h2, h3, h4, h5, h6, p, ul, ol, dl, dd, figure, blockquote, pre { margin: 0; }
html { -webkit-text-size-adjust: 100%; }

/* --- Impianto ------------------------------------------------------------ */
body {
  max-width: var(--width); margin: 0 auto;
  padding: calc(var(--spacing) * 1.5) var(--spacing) calc(var(--spacing) * 3);
  background: var(--color-bg); color: var(--color-text);
  font: var(--font-size)/var(--line-height) var(--font-main);
  overflow-wrap: break-word; /* un URL lunghissimo non deve sfondare la colonna */
}

/* Stacco fra i blocchi di primo livello: elenco, nuvola di tag, form, paginazione. */
main > * + * { margin-top: var(--spacing); }

a { color: var(--color-link); text-underline-offset: 0.15em; }
a:hover { text-decoration-thickness: 2px; }
:focus-visible { outline: 2px solid var(--color-accent); outline-offset: 2px; border-radius: 2px; }

/* --- Testata ------------------------------------------------------------- */
.site-header { margin-bottom: calc(var(--spacing) * 2); }
.site-title { font-size: 1.4rem; line-height: 1.2; font-weight: 700; }
.site-title a { color: var(--color-text); text-decoration: none; }
.site-title a:hover { text-decoration: underline; }
.site-nav { margin-top: 0.5rem; display: flex; flex-wrap: wrap; gap: 0.3rem 1rem; }
.site-nav a { color: var(--color-muted); text-decoration: none; font-size: 0.95rem; }
.site-nav a:hover { color: var(--color-link); text-decoration: underline; }

/* --- Post ---------------------------------------------------------------- */
.post-title { font-size: 1.9rem; line-height: 1.2; }
.post-meta { margin-top: 0.4rem; color: var(--color-muted); font-size: 0.9rem; }

.post-content { margin-top: var(--spacing); }
.post-content > * + * { margin-top: 1em; }
.post-content h2, .post-content h3, .post-content h4,
.post-content h5, .post-content h6 { margin-top: 1.8em; line-height: 1.25; }
.post-content h2 { font-size: 1.4rem; }
.post-content h3 { font-size: 1.2rem; }
.post-content h4 { font-size: 1.05rem; }
.post-content h5, .post-content h6 { font-size: 1rem; color: var(--color-muted); }
.post-content ul, .post-content ol { padding-left: 1.3em; }
.post-content li + li { margin-top: 0.3em; }
.post-content blockquote {
  border-left: 3px solid var(--color-border);
  padding-left: 1em; color: var(--color-muted);
}
.post-content img { max-width: 100%; height: auto; display: block; }
.post-content hr { border: 0; border-top: 1px solid var(--color-border); margin: 2em 0; }
.post-content dt { font-weight: 700; }
.post-content dd { margin-left: 1.3em; }

code {
  font-family: var(--font-mono); font-size: 0.9em;
  background: var(--color-code-bg); padding: 0.15em 0.35em; border-radius: 3px;
}
pre {
  background: var(--color-code-bg); padding: 0.9em 1em; border-radius: 4px;
  overflow-x: auto;
}
pre code { background: none; padding: 0; font-size: 0.875em; }

/* display:block è ciò che rende la tabella scrollabile senza wrapper nel markup. */
.post-content table {
  display: block; width: fit-content; max-width: 100%;
  overflow-x: auto; border-collapse: collapse;
  font-size: 0.95em;
}
.post-content th, .post-content td {
  border: 1px solid var(--color-border); padding: 0.4em 0.7em; text-align: left;
}
.post-content th { background: var(--color-code-bg); }

/* --- Riquadri e note ----------------------------------------------------- */
.callout {
  border-left: 3px solid var(--color-accent); background: var(--color-code-bg);
  padding: 0.8em 1em; border-radius: 0 4px 4px 0;
}
.callout > * + * { margin-top: 0.6em; }
.callout-title { font-weight: 700; }
.callout-tip { border-left-color: #1a7f4b; }
.callout-warning { border-left-color: #a35a00; }
.callout-danger { border-left-color: #b3261e; }

.footnotes { margin-top: calc(var(--spacing) * 2); font-size: 0.9rem; color: var(--color-muted); }
.footnotes hr { border: 0; border-top: 1px solid var(--color-border); margin-bottom: 1em; }
.footnotes li + li { margin-top: 0.4em; }
.footnote-backref { text-decoration: none; margin-left: 0.3em; }

/* --- Tag, voti, navigazione ---------------------------------------------- */
.post-tags, .tag-cloud {
  list-style: none; padding: 0; margin: 0;
  display: flex; flex-wrap: wrap; gap: 0.4rem;
}
.post-tags { margin-top: calc(var(--spacing) * 1.5); }
.post-tags a, .tag-cloud a {
  display: inline-block; padding: 0.15em 0.6em;
  border: 1px solid var(--color-border); border-radius: 999px;
  color: var(--color-muted); font-size: 0.85rem; text-decoration: none;
}
.post-tags a:hover, .tag-cloud a:hover { border-color: var(--color-link); color: var(--color-link); }

.upvote { margin-top: calc(var(--spacing) * 1.5); }
.upvote-button {
  font: inherit; font-size: 0.95rem; cursor: pointer;
  padding: 0.4em 0.9em;
  color: var(--color-text); background: var(--color-bg);
  border: 1px solid var(--color-border); border-radius: 6px;
}
.upvote-button:hover { border-color: var(--color-accent); color: var(--color-accent); }
.upvote-icon { color: var(--color-accent); }
.upvote-count { font-variant-numeric: tabular-nums; }
.upvote-button[aria-pressed="true"] { border-color: var(--color-accent); color: var(--color-accent); }

.post-nav {
  display: flex; justify-content: space-between; gap: 1rem;
  margin-top: calc(var(--spacing) * 2); padding-top: var(--spacing);
  border-top: 1px solid var(--color-border);
  font-size: 0.95rem;
}
.post-nav .next { margin-left: auto; text-align: right; }

/* --- Elenchi ------------------------------------------------------------- */
.post-list { list-style: none; padding: 0; margin: 0; }
.post-list li + li { margin-top: 1.1rem; }
.post-list time {
  color: var(--color-muted); font-size: 0.85rem;
  font-variant-numeric: tabular-nums; margin-right: 0.5rem;
}
.post-list a { text-decoration: none; }
.post-list a:hover { text-decoration: underline; }
.post-list-description { margin-top: 0.2rem; color: var(--color-muted); font-size: 0.92rem; }

/* --- Iscrizione ---------------------------------------------------------- */
.subscribe-form {
  display: flex; flex-wrap: wrap; align-items: center; gap: 0.6rem;
  margin-top: var(--spacing);
}
.subscribe-form label { flex: 1 0 100%; font-size: 0.9rem; color: var(--color-muted); }
.subscribe-form input {
  font: inherit; flex: 1 1 16rem; min-width: 0;
  padding: 0.45em 0.7em;
  color: var(--color-text); background: var(--color-bg);
  border: 1px solid var(--color-border); border-radius: 6px;
}
.subscribe-form button {
  font: inherit; padding: 0.45em 1.1em; cursor: pointer;
  color: var(--color-bg); background: var(--color-accent);
  border: 1px solid var(--color-accent); border-radius: 6px;
}

/* --- Paginazione e piede ------------------------------------------------- */
.pagination {
  display: flex; align-items: center; justify-content: space-between; gap: 1rem;
  margin-top: calc(var(--spacing) * 2);
  font-size: 0.95rem;
}
.pagination-info { color: var(--color-muted); font-variant-numeric: tabular-nums; }
.pagination [rel="next"] { margin-left: auto; }

.site-footer {
  margin-top: calc(var(--spacing) * 3); padding-top: var(--spacing);
  border-top: 1px solid var(--color-border);
  color: var(--color-muted); font-size: 0.875rem;
}

@media (max-width: 34rem) {
  :root { --font-size: 1rem; --spacing: 1.1rem; }
  .post-title { font-size: 1.55rem; }
  .post-nav, .pagination { flex-direction: column; align-items: flex-start; }
  .post-nav .next, .pagination [rel="next"] { margin-left: 0; text-align: left; }
}
CSS,
    ],

    'serif' => [
        'title'       => 'Serif',
        'description' => 'Impaginazione da libro: grazie, testo grande, righe ariose.',
        'sort_order'  => 10,
        'css'         => <<<'CSS'
/* ==========================================================================
   Serif — impaginazione da libro.
   ========================================================================== */

:root {
  --width: 40rem;
  --font-main: "Iowan Old Style", "Palatino Linotype", Palatino, Georgia, "Times New Roman", serif;
  --font-mono: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace;
  --font-size: 1.1875rem;
  --line-height: 1.75;
  --color-bg: #fdfdfb;
  --color-text: #22201d;
  --color-muted: #5b564f;
  --color-link: #8a3324;
  --color-border: #ddd8cd;
  --color-code-bg: #f2efe7;
  --color-accent: #8a3324;
  --spacing: 1.6rem;
}

@media (prefers-color-scheme: dark) {
  :root {
    --color-bg: #1b1a18;
    --color-text: #e9e4da;
    --color-muted: #a9a294;
    --color-link: #e8a08c;
    --color-border: #3a3630;
    --color-code-bg: #26241f;
    --color-accent: #e8a08c;
  }
}

/* --- Reset minimo -------------------------------------------------------- */
*, *::before, *::after { box-sizing: border-box; }
body, h1, h2, h3, h4, h5, h6, p, ul, ol, dl, dd, figure, blockquote, pre { margin: 0; }
html { -webkit-text-size-adjust: 100%; }

/* --- Impianto ------------------------------------------------------------ */
body {
  max-width: var(--width); margin: 0 auto;
  padding: calc(var(--spacing) * 2) var(--spacing) calc(var(--spacing) * 3);
  background: var(--color-bg); color: var(--color-text);
  font: var(--font-size)/var(--line-height) var(--font-main);
  overflow-wrap: break-word; /* un URL lunghissimo non deve sfondare la colonna */
}

/* Stacco fra i blocchi di primo livello: elenco, nuvola di tag, form, paginazione. */
main > * + * { margin-top: var(--spacing); }

a { color: var(--color-link); text-underline-offset: 0.18em; }
:focus-visible { outline: 2px solid var(--color-accent); outline-offset: 3px; }

/* --- Testata: centrata come un frontespizio ------------------------------ */
.site-header {
  text-align: center;
  margin-bottom: calc(var(--spacing) * 2.5); padding-bottom: var(--spacing);
  border-bottom: 1px solid var(--color-border);
}
.site-title { font-size: 1.7rem; font-weight: 400; letter-spacing: 0.02em; }
.site-title a { color: var(--color-text); text-decoration: none; }
.site-nav {
  margin-top: 0.7rem;
  display: flex; flex-wrap: wrap; justify-content: center; gap: 0.3rem 1.2rem;
  font-size: 0.95rem; font-variant: small-caps; letter-spacing: 0.06em;
}
.site-nav a { color: var(--color-muted); text-decoration: none; }
.site-nav a:hover { color: var(--color-link); }

/* --- Post ---------------------------------------------------------------- */
.post-title { font-size: 2.1rem; font-weight: 400; line-height: 1.18; text-align: center; }
.post-meta {
  margin-top: 0.6rem; text-align: center;
  color: var(--color-muted); font-size: 0.9rem;
  font-variant: small-caps; letter-spacing: 0.08em;
}

.post-content { margin-top: calc(var(--spacing) * 1.5); }
.post-content > * + * { margin-top: 1em; }
/* Rientro invece dello stacco fra paragrafi consecutivi: come in un romanzo. */
.post-content p + p { margin-top: 0; text-indent: 1.6em; }
.post-content h2, .post-content h3, .post-content h4,
.post-content h5, .post-content h6 { margin-top: 1.9em; font-weight: 400; line-height: 1.25; }
.post-content h2 { font-size: 1.5rem; }
.post-content h3 { font-size: 1.25rem; font-style: italic; }
.post-content h4, .post-content h5, .post-content h6 { font-size: 1.08rem; font-style: italic; }
.post-content ul, .post-content ol { padding-left: 1.4em; }
.post-content blockquote {
  margin-inline: 1.5em; font-style: italic; color: var(--color-muted);
}
.post-content blockquote p + p { text-indent: 0; }
.post-content img { max-width: 100%; height: auto; display: block; margin-inline: auto; }
/* Filetto ornamentale al posto della riga piena. */
.post-content hr { border: 0; margin: 2.2em 0; text-align: center; color: var(--color-muted); }
.post-content hr::after { content: "\00A7"; font-size: 1.1rem; }
.post-content dt { font-weight: 700; }
.post-content dd { margin-left: 1.4em; }

code {
  font-family: var(--font-mono); font-size: 0.85em;
  background: var(--color-code-bg); padding: 0.1em 0.3em;
}
pre {
  background: var(--color-code-bg); border: 1px solid var(--color-border);
  padding: 0.9em 1em; overflow-x: auto; line-height: 1.5;
}
pre code { background: none; padding: 0; font-size: 0.82em; }

.post-content table {
  display: block; width: fit-content; max-width: 100%;
  overflow-x: auto; border-collapse: collapse;
  font-size: 0.92em;
}
.post-content th, .post-content td { padding: 0.45em 0.8em; text-align: left; }
.post-content thead th { border-bottom: 2px solid var(--color-text); }
.post-content tbody tr + tr td { border-top: 1px solid var(--color-border); }

/* --- Riquadri e note ----------------------------------------------------- */
.callout {
  border: 1px solid var(--color-border); background: var(--color-code-bg);
  padding: 1em 1.2em; font-size: 0.95em;
}
.callout > * + * { margin-top: 0.6em; }
.callout p + p { text-indent: 0; }
.callout-title { font-variant: small-caps; letter-spacing: 0.08em; color: var(--color-accent); }
.callout-warning { border-color: #a35a00; }
.callout-danger { border-color: #a4231d; }

.footnotes { margin-top: calc(var(--spacing) * 2); font-size: 0.88rem; color: var(--color-muted); }
.footnotes hr { border: 0; border-top: 1px solid var(--color-border); margin-bottom: 1em; }
.footnotes p { text-indent: 0; }
.footnote-backref { text-decoration: none; margin-left: 0.3em; }

/* --- Tag, voti, navigazione ---------------------------------------------- */
.post-tags, .tag-cloud {
  list-style: none; padding: 0; margin: 0;
  display: flex; flex-wrap: wrap; gap: 0.3rem 1rem;
  font-size: 0.9rem; font-style: italic;
}
.post-tags { margin-top: calc(var(--spacing) * 1.5); justify-content: center; }
.post-tags a, .tag-cloud a { color: var(--color-muted); }
.post-tags a:hover, .tag-cloud a:hover { color: var(--color-link); }

.upvote { margin-top: calc(var(--spacing) * 1.5); text-align: center; }
.upvote-button {
  font: inherit; font-size: 0.95rem; cursor: pointer;
  padding: 0.4em 1.1em; background: transparent;
  color: var(--color-text); border: 1px solid var(--color-border);
}
.upvote-button:hover { border-color: var(--color-accent); color: var(--color-accent); }
.upvote-icon { color: var(--color-accent); }
.upvote-count { font-variant-numeric: tabular-nums; }
.upvote-button[aria-pressed="true"] { border-color: var(--color-accent); color: var(--color-accent); }

.post-nav {
  display: flex; justify-content: space-between; gap: 1rem;
  margin-top: calc(var(--spacing) * 2); padding-top: var(--spacing);
  border-top: 1px solid var(--color-border);
  font-size: 0.92rem; font-style: italic;
}
.post-nav .next { margin-left: auto; text-align: right; }

/* --- Elenchi ------------------------------------------------------------- */
.post-list { list-style: none; padding: 0; margin: 0; }
.post-list li + li { margin-top: 1.2rem; }
.post-list time {
  display: block; color: var(--color-muted);
  font-size: 0.82rem; font-variant: small-caps; letter-spacing: 0.08em;
}
.post-list a { text-decoration: none; font-size: 1.1rem; }
.post-list a:hover { text-decoration: underline; }
.post-list-description { margin-top: 0.2rem; color: var(--color-muted); font-size: 0.95rem; }

/* --- Iscrizione ---------------------------------------------------------- */
.subscribe-form { display: flex; flex-wrap: wrap; gap: 0.6rem; margin-top: var(--spacing); }
.subscribe-form label { flex: 1 0 100%; font-size: 0.9rem; font-style: italic; color: var(--color-muted); }
.subscribe-form input {
  font: inherit; flex: 1 1 16rem; min-width: 0;
  padding: 0.45em 0.7em;
  color: var(--color-text); background: var(--color-bg);
  border: 1px solid var(--color-border);
}
.subscribe-form button {
  font: inherit; padding: 0.45em 1.2em; cursor: pointer;
  color: var(--color-bg); background: var(--color-accent);
  border: 1px solid var(--color-accent);
}

/* --- Paginazione e piede ------------------------------------------------- */
.pagination {
  display: flex; align-items: center; justify-content: space-between; gap: 1rem;
  margin-top: calc(var(--spacing) * 2);
  font-size: 0.92rem; font-style: italic;
}
.pagination-info { color: var(--color-muted); font-style: normal; font-variant-numeric: tabular-nums; }
.pagination [rel="next"] { margin-left: auto; }

.site-footer {
  margin-top: calc(var(--spacing) * 3); padding-top: var(--spacing);
  border-top: 1px solid var(--color-border); text-align: center;
  color: var(--color-muted); font-size: 0.85rem;
}

@media (max-width: 34rem) {
  :root { --font-size: 1.0625rem; --spacing: 1.1rem; }
  .post-title { font-size: 1.65rem; }
  .post-content blockquote { margin-inline: 0.8em; }
  .post-nav, .pagination { flex-direction: column; align-items: flex-start; }
  .post-nav .next, .pagination [rel="next"] { margin-left: 0; text-align: left; }
}
CSS,
    ],

    'mono' => [
        'title'       => 'Monospazio',
        'description' => 'Tutto a spaziatura fissa, estetica da terminale ma su carta bianca.',
        'sort_order'  => 20,
        'css'         => <<<'CSS'
/* ==========================================================================
   Monospazio — una sola famiglia di caratteri, griglia visibile.
   ========================================================================== */

:root {
  --width: 44rem;
  --font-main: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, "DejaVu Sans Mono", "Liberation Mono", monospace;
  --font-mono: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, "DejaVu Sans Mono", "Liberation Mono", monospace;
  --font-size: 0.9375rem;
  --line-height: 1.6;
  --color-bg: #fbfbfb;
  --color-text: #1f1f1f;
  --color-muted: #565656;
  --color-link: #0f5ca8;
  --color-border: #d0d0d0;
  --color-code-bg: #f0f0f0;
  --color-accent: #0f5ca8;
  --spacing: 1.4rem;
}

@media (prefers-color-scheme: dark) {
  :root {
    --color-bg: #151515;
    --color-text: #e4e4e4;
    --color-muted: #9e9e9e;
    --color-link: #79b8f3;
    --color-border: #333333;
    --color-code-bg: #1f1f1f;
    --color-accent: #79b8f3;
  }
}

/* --- Reset minimo -------------------------------------------------------- */
*, *::before, *::after { box-sizing: border-box; }
body, h1, h2, h3, h4, h5, h6, p, ul, ol, dl, dd, figure, blockquote, pre { margin: 0; }
html { -webkit-text-size-adjust: 100%; }

/* --- Impianto: niente angoli arrotondati, la griglia va rispettata ------- */
body {
  max-width: var(--width); margin: 0 auto;
  padding: calc(var(--spacing) * 1.5) var(--spacing) calc(var(--spacing) * 3);
  background: var(--color-bg); color: var(--color-text);
  font: var(--font-size)/var(--line-height) var(--font-main);
  overflow-wrap: break-word; /* un URL lunghissimo non deve sfondare la colonna */
}

/* Stacco fra i blocchi di primo livello: elenco, nuvola di tag, form, paginazione. */
main > * + * { margin-top: var(--spacing); }

a { color: var(--color-link); text-underline-offset: 0.2em; }
:focus-visible { outline: 2px solid var(--color-accent); outline-offset: 2px; }

/* --- Testata ------------------------------------------------------------- */
.site-header {
  margin-bottom: calc(var(--spacing) * 1.5); padding-bottom: var(--spacing);
  border-bottom: 1px dashed var(--color-border);
}
.site-title { font-size: 1.15rem; font-weight: 700; letter-spacing: -0.02em; }
.site-title a { color: var(--color-text); text-decoration: none; }
.site-title a:hover { color: var(--color-link); }
.site-nav { margin-top: 0.6rem; display: flex; flex-wrap: wrap; gap: 0.3rem 0.9rem; font-size: 0.875rem; }
.site-nav a { color: var(--color-muted); text-decoration: none; }
.site-nav a::before { content: "["; }
.site-nav a::after { content: "]"; }
.site-nav a:hover { color: var(--color-link); }

/* --- Post ---------------------------------------------------------------- */
.post-title { font-size: 1.4rem; line-height: 1.25; font-weight: 700; }
.post-meta { margin-top: 0.4rem; color: var(--color-muted); font-size: 0.85rem; }

.post-content { margin-top: var(--spacing); }
.post-content > * + * { margin-top: 1em; }
.post-content h2, .post-content h3, .post-content h4,
.post-content h5, .post-content h6 { margin-top: 2em; font-size: 1rem; line-height: 1.3; }
.post-content h2 { font-size: 1.1rem; }
/* I cancelletti richiamano la sorgente Markdown del titolo. */
.post-content h2::before { content: "## "; color: var(--color-muted); }
.post-content h3::before { content: "### "; color: var(--color-muted); }
.post-content h4::before { content: "#### "; color: var(--color-muted); }
.post-content ul, .post-content ol { padding-left: 2ch; }
.post-content ul { list-style: none; }
.post-content ul > li::before { content: "- "; color: var(--color-muted); margin-left: -2ch; }
.post-content li + li { margin-top: 0.25em; }
.post-content blockquote {
  border-left: 2px solid var(--color-border);
  padding-left: 1ch; color: var(--color-muted);
}
.post-content img { max-width: 100%; height: auto; display: block; border: 1px solid var(--color-border); }
.post-content hr { border: 0; border-top: 1px dashed var(--color-border); margin: 2em 0; }
.post-content dt { font-weight: 700; }
.post-content dd { margin-left: 2ch; }

/* --font-mono coincide con --font-main, ma resta esplicito: chi personalizza
   può cambiare il solo carattere del codice senza toccare il resto. */
code { font-family: var(--font-mono); background: var(--color-code-bg); padding: 0 0.3ch; }
pre {
  font-family: var(--font-mono); background: var(--color-code-bg);
  border: 1px solid var(--color-border); padding: 0.8em 1ch;
  overflow-x: auto; font-size: 0.92em;
}
pre code { background: none; padding: 0; }

.post-content table {
  display: block; width: fit-content; max-width: 100%;
  overflow-x: auto; border-collapse: collapse;
  font-size: 0.92em;
}
.post-content th, .post-content td {
  border: 1px solid var(--color-border); padding: 0.3em 1ch; text-align: left;
}
.post-content th { background: var(--color-code-bg); }

/* --- Riquadri e note ----------------------------------------------------- */
.callout { border: 1px dashed var(--color-accent); padding: 0.8em 1ch; font-size: 0.95em; }
.callout > * + * { margin-top: 0.5em; }
.callout-title { font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
.callout-title::before { content: "! "; color: var(--color-accent); }
.callout-warning { border-color: #8a5000; }
.callout-danger { border-color: #a4231d; }

.footnotes { margin-top: calc(var(--spacing) * 2); font-size: 0.85rem; color: var(--color-muted); }
.footnotes hr { border: 0; border-top: 1px dashed var(--color-border); margin-bottom: 1em; }
.footnotes ol { padding-left: 3ch; }
.footnote-backref { text-decoration: none; margin-left: 1ch; }

/* --- Tag, voti, navigazione ---------------------------------------------- */
.post-tags, .tag-cloud {
  list-style: none; padding: 0; margin: 0;
  display: flex; flex-wrap: wrap; gap: 0.3rem 1ch; font-size: 0.85rem;
}
.post-tags { margin-top: calc(var(--spacing) * 1.5); }
.post-tags a, .tag-cloud a { color: var(--color-muted); text-decoration: none; }
.post-tags a::before, .tag-cloud a::before { content: "#"; color: var(--color-accent); }
.post-tags a:hover, .tag-cloud a:hover { color: var(--color-link); text-decoration: underline; }

.upvote { margin-top: calc(var(--spacing) * 1.5); }
.upvote-button {
  font: inherit; cursor: pointer; padding: 0.35em 1.2ch;
  color: var(--color-text); background: var(--color-bg);
  border: 1px solid var(--color-border);
}
.upvote-button:hover { background: var(--color-code-bg); border-color: var(--color-accent); }
.upvote-icon { color: var(--color-accent); }
.upvote-count { font-variant-numeric: tabular-nums; }
.upvote-button[aria-pressed="true"] { border-color: var(--color-accent); color: var(--color-accent); }

.post-nav {
  display: flex; justify-content: space-between; gap: 1rem;
  margin-top: calc(var(--spacing) * 2); padding-top: var(--spacing);
  border-top: 1px dashed var(--color-border);
  font-size: 0.875rem;
}
.post-nav .next { margin-left: auto; text-align: right; }

/* --- Elenchi ------------------------------------------------------------- */
.post-list { list-style: none; padding: 0; margin: 0; }
.post-list li + li { margin-top: 0.9rem; }
.post-list time { color: var(--color-muted); margin-right: 1ch; }
.post-list a { text-decoration: none; }
.post-list a:hover { text-decoration: underline; }
.post-list-description { margin-top: 0.15rem; color: var(--color-muted); font-size: 0.875rem; }

/* --- Iscrizione ---------------------------------------------------------- */
.subscribe-form { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: var(--spacing); }
.subscribe-form label { flex: 1 0 100%; color: var(--color-muted); font-size: 0.85rem; }
.subscribe-form input {
  font: inherit; flex: 1 1 16rem; min-width: 0; padding: 0.4em 1ch;
  color: var(--color-text); background: var(--color-bg);
  border: 1px solid var(--color-border);
}
.subscribe-form button {
  font: inherit; cursor: pointer; padding: 0.4em 2ch;
  color: var(--color-bg); background: var(--color-accent);
  border: 1px solid var(--color-accent);
}

/* --- Paginazione e piede ------------------------------------------------- */
.pagination {
  display: flex; align-items: center; justify-content: space-between; gap: 1rem;
  margin-top: calc(var(--spacing) * 2);
  font-size: 0.875rem;
}
.pagination-info { color: var(--color-muted); font-variant-numeric: tabular-nums; }
.pagination [rel="next"] { margin-left: auto; }

.site-footer {
  margin-top: calc(var(--spacing) * 3); padding-top: var(--spacing);
  border-top: 1px dashed var(--color-border);
  color: var(--color-muted); font-size: 0.8125rem;
}

@media (max-width: 34rem) {
  :root { --spacing: 1rem; }
  .post-nav, .pagination { flex-direction: column; align-items: flex-start; }
  .post-nav .next, .pagination [rel="next"] { margin-left: 0; text-align: left; }
}
CSS,
    ],

    'notte' => [
        'title'       => 'Notte',
        'description' => 'Scuro sempre, indipendentemente dalle preferenze di sistema.',
        'sort_order'  => 30,
        'css'         => <<<'CSS'
/* ==========================================================================
   Notte — scuro per scelta, non per preferenza di sistema.
   ========================================================================== */

:root {
  color-scheme: dark; /* fa scurire anche i controlli nativi del browser */
  --width: 46rem;
  --font-main: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
  --font-mono: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, "Liberation Mono", monospace;
  --font-size: 1.0625rem;
  --line-height: 1.7;
  --color-bg: #101215;
  --color-text: #e8e8e8;
  --color-muted: #a2a8b0;
  --color-link: #79c0ff;
  --color-border: #262b31;
  --color-code-bg: #191d22;
  --color-accent: #ffb86c;
  --spacing: 1.5rem;
}

/* --- Reset minimo -------------------------------------------------------- */
*, *::before, *::after { box-sizing: border-box; }
body, h1, h2, h3, h4, h5, h6, p, ul, ol, dl, dd, figure, blockquote, pre { margin: 0; }
html { -webkit-text-size-adjust: 100%; background: #101215; }

/* --- Impianto ------------------------------------------------------------ */
body {
  max-width: var(--width); margin: 0 auto;
  padding: calc(var(--spacing) * 1.5) var(--spacing) calc(var(--spacing) * 3);
  background: var(--color-bg); color: var(--color-text);
  font: var(--font-size)/var(--line-height) var(--font-main);
  overflow-wrap: break-word; /* un URL lunghissimo non deve sfondare la colonna */
}

/* Stacco fra i blocchi di primo livello: elenco, nuvola di tag, form, paginazione. */
main > * + * { margin-top: var(--spacing); }

a { color: var(--color-link); text-underline-offset: 0.15em; }
a:hover { text-decoration-thickness: 2px; }
:focus-visible { outline: 2px solid var(--color-accent); outline-offset: 2px; border-radius: 2px; }

/* --- Testata ------------------------------------------------------------- */
.site-header { margin-bottom: calc(var(--spacing) * 2); }
.site-title { font-size: 1.4rem; line-height: 1.2; font-weight: 600; }
.site-title a { color: var(--color-text); text-decoration: none; }
.site-title a:hover { color: var(--color-accent); }
.site-nav { margin-top: 0.5rem; display: flex; flex-wrap: wrap; gap: 0.3rem 1rem; font-size: 0.95rem; }
.site-nav a { color: var(--color-muted); text-decoration: none; }
.site-nav a:hover { color: var(--color-link); text-decoration: underline; }

/* --- Post ---------------------------------------------------------------- */
.post-title { font-size: 1.9rem; line-height: 1.2; font-weight: 600; }
.post-meta { margin-top: 0.4rem; color: var(--color-muted); font-size: 0.9rem; }

.post-content { margin-top: var(--spacing); }
.post-content > * + * { margin-top: 1em; }
.post-content h2, .post-content h3, .post-content h4,
.post-content h5, .post-content h6 { margin-top: 1.9em; line-height: 1.25; font-weight: 600; }
.post-content h2 { font-size: 1.4rem; }
.post-content h3 { font-size: 1.2rem; }
.post-content h4 { font-size: 1.05rem; }
.post-content h5, .post-content h6 { font-size: 1rem; color: var(--color-muted); }
.post-content ul, .post-content ol { padding-left: 1.3em; }
.post-content li + li { margin-top: 0.3em; }
.post-content li::marker { color: var(--color-accent); }
.post-content blockquote {
  border-left: 3px solid var(--color-accent);
  padding-left: 1em; color: var(--color-muted);
}
/* Le immagini chiare abbagliano su fondo scuro: un filo di trasparenza aiuta. */
.post-content img { max-width: 100%; height: auto; display: block; opacity: 0.92; }
.post-content img:hover { opacity: 1; }
.post-content hr { border: 0; border-top: 1px solid var(--color-border); margin: 2em 0; }
.post-content dt { font-weight: 600; color: var(--color-accent); }
.post-content dd { margin-left: 1.3em; }

code {
  font-family: var(--font-mono); font-size: 0.9em;
  background: var(--color-code-bg); border: 1px solid var(--color-border);
  padding: 0.1em 0.35em; border-radius: 3px;
}
pre {
  background: var(--color-code-bg); border: 1px solid var(--color-border);
  padding: 0.9em 1em; border-radius: 6px; overflow-x: auto;
}
pre code { background: none; border: 0; padding: 0; font-size: 0.875em; }

.post-content table {
  display: block; width: fit-content; max-width: 100%;
  overflow-x: auto; border-collapse: collapse;
  font-size: 0.95em;
}
.post-content th, .post-content td {
  border: 1px solid var(--color-border); padding: 0.4em 0.7em; text-align: left;
}
.post-content th { background: var(--color-code-bg); }

/* --- Riquadri e note ----------------------------------------------------- */
.callout {
  border: 1px solid var(--color-border); border-left: 3px solid var(--color-accent);
  background: var(--color-code-bg); padding: 0.85em 1em; border-radius: 0 6px 6px 0;
}
.callout > * + * { margin-top: 0.6em; }
.callout-title { font-weight: 600; color: var(--color-accent); }
.callout-tip { border-left-color: #7ee2a8; }
.callout-tip .callout-title { color: #7ee2a8; }
.callout-danger { border-left-color: #ff8a80; }
.callout-danger .callout-title { color: #ff8a80; }

.footnotes { margin-top: calc(var(--spacing) * 2); font-size: 0.9rem; color: var(--color-muted); }
.footnotes hr { border: 0; border-top: 1px solid var(--color-border); margin-bottom: 1em; }
.footnotes li + li { margin-top: 0.4em; }
.footnote-backref { text-decoration: none; margin-left: 0.3em; }

/* --- Tag, voti, navigazione ---------------------------------------------- */
.post-tags, .tag-cloud {
  list-style: none; padding: 0; margin: 0;
  display: flex; flex-wrap: wrap; gap: 0.4rem;
}
.post-tags { margin-top: calc(var(--spacing) * 1.5); }
.post-tags a, .tag-cloud a {
  display: inline-block; padding: 0.15em 0.65em;
  background: var(--color-code-bg); border: 1px solid var(--color-border);
  border-radius: 999px; color: var(--color-muted);
  font-size: 0.85rem; text-decoration: none;
}
.post-tags a:hover, .tag-cloud a:hover { color: var(--color-accent); border-color: var(--color-accent); }

.upvote { margin-top: calc(var(--spacing) * 1.5); }
.upvote-button {
  font: inherit; font-size: 0.95rem; cursor: pointer; padding: 0.45em 1em;
  color: var(--color-text); background: var(--color-code-bg);
  border: 1px solid var(--color-border); border-radius: 6px;
}
.upvote-button:hover { border-color: var(--color-accent); color: var(--color-accent); }
.upvote-icon { color: var(--color-accent); }
.upvote-count { font-variant-numeric: tabular-nums; }
.upvote-button[aria-pressed="true"] { border-color: var(--color-accent); color: var(--color-accent); }

.post-nav {
  display: flex; justify-content: space-between; gap: 1rem;
  margin-top: calc(var(--spacing) * 2); padding-top: var(--spacing);
  border-top: 1px solid var(--color-border);
  font-size: 0.95rem;
}
.post-nav .next { margin-left: auto; text-align: right; }

/* --- Elenchi ------------------------------------------------------------- */
.post-list { list-style: none; padding: 0; margin: 0; }
.post-list li + li { margin-top: 1.1rem; }
.post-list time {
  color: var(--color-muted); font-size: 0.85rem;
  font-variant-numeric: tabular-nums; margin-right: 0.5rem;
}
.post-list a { text-decoration: none; }
.post-list a:hover { text-decoration: underline; }
.post-list-description { margin-top: 0.2rem; color: var(--color-muted); font-size: 0.92rem; }

/* --- Iscrizione ---------------------------------------------------------- */
.subscribe-form { display: flex; flex-wrap: wrap; gap: 0.6rem; margin-top: var(--spacing); }
.subscribe-form label { flex: 1 0 100%; font-size: 0.9rem; color: var(--color-muted); }
.subscribe-form input {
  font: inherit; flex: 1 1 16rem; min-width: 0; padding: 0.45em 0.7em;
  color: var(--color-text); background: var(--color-code-bg);
  border: 1px solid var(--color-border); border-radius: 6px;
}
.subscribe-form button {
  font: inherit; cursor: pointer; padding: 0.45em 1.1em;
  color: #1a1205; /* testo scuro sull'ambra: il contrasto regge */
  background: var(--color-accent);
  border: 1px solid var(--color-accent); border-radius: 6px;
}

/* --- Paginazione e piede ------------------------------------------------- */
.pagination {
  display: flex; align-items: center; justify-content: space-between; gap: 1rem;
  margin-top: calc(var(--spacing) * 2);
  font-size: 0.95rem;
}
.pagination-info { color: var(--color-muted); font-variant-numeric: tabular-nums; }
.pagination [rel="next"] { margin-left: auto; }

.site-footer {
  margin-top: calc(var(--spacing) * 3); padding-top: var(--spacing);
  border-top: 1px solid var(--color-border);
  color: var(--color-muted); font-size: 0.875rem;
}

@media (max-width: 34rem) {
  :root { --font-size: 1rem; --spacing: 1.1rem; }
  .post-title { font-size: 1.55rem; }
  .post-nav, .pagination { flex-direction: column; align-items: flex-start; }
  .post-nav .next, .pagination [rel="next"] { margin-left: 0; text-align: left; }
}
CSS,
    ],

    'carta' => [
        'title'       => 'Carta',
        'description' => 'Fondo avorio caldo e inchiostro scuro, come una pagina stampata.',
        'sort_order'  => 40,
        'css'         => <<<'CSS'
/* ==========================================================================
   Carta — avorio caldo, inchiostro seppia. Resta chiaro anche di notte:
   è il punto del tema, quindi nessuna variante prefers-color-scheme.
   ========================================================================== */

:root {
  color-scheme: light;
  --width: 42rem;
  --font-main: "Palatino Linotype", Palatino, "Book Antiqua", Georgia, "Times New Roman", serif;
  --font-mono: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace;
  --font-size: 1.125rem;
  --line-height: 1.72;
  --color-bg: #f6f1e4;
  --color-text: #2b2621;
  --color-muted: #5f574c;
  --color-link: #8f3a1d;
  --color-border: #ddd2ba;
  --color-code-bg: #ece5d3;
  --color-accent: #8f3a1d;
  --spacing: 1.6rem;
}

/* --- Reset minimo -------------------------------------------------------- */
*, *::before, *::after { box-sizing: border-box; }
body, h1, h2, h3, h4, h5, h6, p, ul, ol, dl, dd, figure, blockquote, pre { margin: 0; }
html { -webkit-text-size-adjust: 100%; background: #ece5d3; }

/* Il foglio è più chiaro dello sfondo della finestra: sembra appoggiato. */
body {
  max-width: var(--width); margin: 0 auto; min-height: 100vh;
  padding: calc(var(--spacing) * 2) var(--spacing) calc(var(--spacing) * 3);
  background: var(--color-bg); color: var(--color-text);
  font: var(--font-size)/var(--line-height) var(--font-main);
  overflow-wrap: break-word; /* un URL lunghissimo non deve sfondare la colonna */
  border-inline: 1px solid var(--color-border);
}

/* Stacco fra i blocchi di primo livello: elenco, nuvola di tag, form, paginazione. */
main > * + * { margin-top: var(--spacing); }

a { color: var(--color-link); text-underline-offset: 0.16em; }
:focus-visible { outline: 2px solid var(--color-accent); outline-offset: 3px; }

/* --- Testata ------------------------------------------------------------- */
.site-header {
  margin-bottom: calc(var(--spacing) * 2); padding-bottom: calc(var(--spacing) * 0.8);
  border-bottom: 3px double var(--color-border);
}
.site-title { font-size: 1.6rem; font-weight: 700; letter-spacing: 0.01em; }
.site-title a { color: var(--color-text); text-decoration: none; }
.site-title a:hover { color: var(--color-link); }
.site-nav { margin-top: 0.6rem; display: flex; flex-wrap: wrap; gap: 0.3rem 1.1rem; font-size: 0.92rem; }
.site-nav a { color: var(--color-muted); text-decoration: none; }
.site-nav a:hover { color: var(--color-link); text-decoration: underline; }

/* --- Post ---------------------------------------------------------------- */
.post-title { font-size: 1.95rem; line-height: 1.2; font-weight: 700; }
.post-meta { margin-top: 0.45rem; color: var(--color-muted); font-size: 0.88rem; letter-spacing: 0.03em; }

.post-content { margin-top: calc(var(--spacing) * 1.2); }
.post-content > * + * { margin-top: 1em; }
.post-content h2, .post-content h3, .post-content h4,
.post-content h5, .post-content h6 { margin-top: 1.9em; line-height: 1.25; font-weight: 700; }
.post-content h2 { font-size: 1.4rem; border-bottom: 1px solid var(--color-border); padding-bottom: 0.2em; }
.post-content h3 { font-size: 1.2rem; }
.post-content h4, .post-content h5, .post-content h6 { font-size: 1.05rem; }
.post-content ul, .post-content ol { padding-left: 1.4em; }
.post-content li + li { margin-top: 0.3em; }
.post-content li::marker { color: var(--color-accent); }
.post-content blockquote {
  border-left: 4px solid var(--color-border); padding: 0.2em 0 0.2em 1.1em;
  color: var(--color-muted); font-style: italic;
}
/* La cornice bianca imita il passe-partout di una stampa incollata. */
.post-content img {
  max-width: 100%; height: auto; display: block; margin-inline: auto;
  border: 1px solid var(--color-border); padding: 4px; background: #fffdf6;
}
.post-content hr { border: 0; border-top: 3px double var(--color-border); margin: 2.2em 0; }
.post-content dt { font-weight: 700; }
.post-content dd { margin-left: 1.4em; color: var(--color-muted); }

code {
  font-family: var(--font-mono); font-size: 0.85em;
  background: var(--color-code-bg); padding: 0.12em 0.35em; border-radius: 2px;
}
pre {
  background: var(--color-code-bg); border: 1px solid var(--color-border);
  padding: 0.9em 1em; overflow-x: auto; line-height: 1.5;
}
pre code { background: none; padding: 0; font-size: 0.82em; }

.post-content table {
  display: block; width: fit-content; max-width: 100%;
  overflow-x: auto; border-collapse: collapse;
  font-size: 0.93em;
}
.post-content th, .post-content td {
  border: 1px solid var(--color-border); padding: 0.4em 0.75em; text-align: left;
}
.post-content th { background: var(--color-code-bg); }

/* --- Riquadri e note ----------------------------------------------------- */
.callout {
  border: 1px solid var(--color-border); border-left: 4px solid var(--color-accent);
  background: var(--color-code-bg); padding: 0.9em 1.1em; font-size: 0.96em;
}
.callout > * + * { margin-top: 0.6em; }
.callout-title { font-weight: 700; color: var(--color-accent); }
.callout-tip { border-left-color: #2f6b3d; }
.callout-warning { border-left-color: #8a5000; }
.callout-danger { border-left-color: #9c2420; }

.footnotes { margin-top: calc(var(--spacing) * 2); font-size: 0.88rem; color: var(--color-muted); }
.footnotes hr { border: 0; border-top: 1px solid var(--color-border); margin-bottom: 1em; }
.footnotes li + li { margin-top: 0.4em; }
.footnote-backref { text-decoration: none; margin-left: 0.3em; }

/* --- Tag, voti, navigazione ---------------------------------------------- */
.post-tags, .tag-cloud {
  list-style: none; padding: 0; margin: 0;
  display: flex; flex-wrap: wrap; gap: 0.4rem; font-size: 0.85rem;
}
.post-tags { margin-top: calc(var(--spacing) * 1.5); }
.post-tags a, .tag-cloud a {
  display: inline-block; padding: 0.1em 0.6em;
  border: 1px solid var(--color-border); background: var(--color-code-bg);
  color: var(--color-muted); text-decoration: none;
}
.post-tags a:hover, .tag-cloud a:hover { color: var(--color-link); border-color: var(--color-accent); }

.upvote { margin-top: calc(var(--spacing) * 1.5); }
.upvote-button {
  font: inherit; font-size: 0.92rem; cursor: pointer; padding: 0.4em 1em;
  color: var(--color-text); background: var(--color-code-bg);
  border: 1px solid var(--color-border);
}
.upvote-button:hover { border-color: var(--color-accent); color: var(--color-accent); }
.upvote-icon { color: var(--color-accent); }
.upvote-count { font-variant-numeric: tabular-nums; }
.upvote-button[aria-pressed="true"] { border-color: var(--color-accent); color: var(--color-accent); }

.post-nav {
  display: flex; justify-content: space-between; gap: 1rem;
  margin-top: calc(var(--spacing) * 2); padding-top: var(--spacing);
  border-top: 3px double var(--color-border);
  font-size: 0.92rem;
}
.post-nav .next { margin-left: auto; text-align: right; }

/* --- Elenchi ------------------------------------------------------------- */
.post-list { list-style: none; padding: 0; margin: 0; }
.post-list li + li { margin-top: 1.1rem; padding-top: 1.1rem; border-top: 1px solid var(--color-border); }
.post-list time {
  color: var(--color-muted); font-size: 0.85rem;
  font-variant-numeric: tabular-nums; margin-right: 0.6rem;
}
.post-list a { text-decoration: none; }
.post-list a:hover { text-decoration: underline; }
.post-list-description { margin-top: 0.25rem; color: var(--color-muted); font-size: 0.95rem; }

/* --- Iscrizione ---------------------------------------------------------- */
.subscribe-form { display: flex; flex-wrap: wrap; gap: 0.6rem; margin-top: var(--spacing); }
.subscribe-form label { flex: 1 0 100%; font-size: 0.9rem; color: var(--color-muted); }
.subscribe-form input {
  font: inherit; flex: 1 1 16rem; min-width: 0; padding: 0.45em 0.7em;
  color: var(--color-text); background: #fffdf6;
  border: 1px solid var(--color-border);
}
.subscribe-form button {
  font: inherit; cursor: pointer; padding: 0.45em 1.2em;
  color: #fdf9ef; background: var(--color-accent);
  border: 1px solid var(--color-accent);
}

/* --- Paginazione e piede ------------------------------------------------- */
.pagination {
  display: flex; align-items: center; justify-content: space-between; gap: 1rem;
  margin-top: calc(var(--spacing) * 2);
  font-size: 0.92rem;
}
.pagination-info { color: var(--color-muted); font-variant-numeric: tabular-nums; }
.pagination [rel="next"] { margin-left: auto; }

.site-footer {
  margin-top: calc(var(--spacing) * 3); padding-top: var(--spacing);
  border-top: 3px double var(--color-border);
  color: var(--color-muted); font-size: 0.85rem;
}

@media (max-width: 34rem) {
  :root { --font-size: 1.0625rem; --spacing: 1.1rem; }
  body { border-inline: 0; }
  .post-title { font-size: 1.6rem; }
  .post-nav, .pagination { flex-direction: column; align-items: flex-start; }
  .post-nav .next, .pagination [rel="next"] { margin-left: 0; text-align: left; }
}
CSS,
    ],

    'brutalista' => [
        'title'       => 'Brutalista',
        'description' => 'Bordi spessi, spigoli vivi, contrasto massimo. Nessuna concessione.',
        'sort_order'  => 50,
        'css'         => <<<'CSS'
/* ==========================================================================
   Brutalista — bianco, nero, un giallo. Nessun raggio, nessuna sfumatura.
   ========================================================================== */

:root {
  color-scheme: light;
  --width: 48rem;
  --font-main: "Helvetica Neue", Helvetica, Arial, "Liberation Sans", sans-serif;
  --font-mono: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace;
  --font-size: 1.0625rem;
  --line-height: 1.55;
  --color-bg: #ffffff;
  --color-text: #000000;
  --color-muted: #444444;
  --color-link: #0000ee;
  --color-border: #000000;
  --color-code-bg: #ececec;
  --color-accent: #ffe600;
  --spacing: 1.5rem;
}

/* --- Reset minimo: il raggio zero è parte del tema, non una svista ------- */
*, *::before, *::after { box-sizing: border-box; border-radius: 0; }
body, h1, h2, h3, h4, h5, h6, p, ul, ol, dl, dd, figure, blockquote, pre { margin: 0; }
html { -webkit-text-size-adjust: 100%; }

body {
  max-width: var(--width); margin: 0 auto;
  padding: var(--spacing) var(--spacing) calc(var(--spacing) * 3);
  background: var(--color-bg); color: var(--color-text);
  font: var(--font-size)/var(--line-height) var(--font-main);
  overflow-wrap: break-word; /* un URL lunghissimo non deve sfondare la colonna */
}

/* Stacco fra i blocchi di primo livello: elenco, nuvola di tag, form, paginazione. */
main > * + * { margin-top: var(--spacing); }

a { color: var(--color-link); text-decoration: underline; text-decoration-thickness: 2px; }
a:hover { background: var(--color-accent); color: var(--color-text); }
/* Il focus è un blocco pieno, non un filo: si vede da lontano. */
:focus-visible { outline: 4px solid var(--color-accent); background: var(--color-accent); color: #000; }

/* --- Testata ------------------------------------------------------------- */
.site-header {
  border: 4px solid var(--color-border); padding: var(--spacing);
  margin-bottom: calc(var(--spacing) * 1.5);
}
.site-title { font-size: 2rem; line-height: 1; font-weight: 800; text-transform: uppercase; letter-spacing: -0.03em; }
.site-title a { color: var(--color-text); text-decoration: none; }
.site-title a:hover { background: var(--color-accent); }
.site-nav { margin-top: 0.9rem; display: flex; flex-wrap: wrap; gap: 0.5rem; }
.site-nav a {
  border: 2px solid var(--color-border); padding: 0.15em 0.6em;
  color: var(--color-text); text-decoration: none;
  font-size: 0.9rem; font-weight: 700; text-transform: uppercase;
}
.site-nav a:hover { background: var(--color-text); color: var(--color-bg); }

/* --- Post ---------------------------------------------------------------- */
.post-title { font-size: 2.2rem; line-height: 1.05; font-weight: 800; text-transform: uppercase; letter-spacing: -0.03em; }
.post-meta { margin-top: 0.5rem; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; }

.post-content { margin-top: var(--spacing); }
.post-content > * + * { margin-top: 1em; }
.post-content h2, .post-content h3, .post-content h4,
.post-content h5, .post-content h6 { margin-top: 1.8em; line-height: 1.15; font-weight: 800; text-transform: uppercase; }
.post-content h2 { font-size: 1.5rem; border-bottom: 4px solid var(--color-border); padding-bottom: 0.15em; }
.post-content h3 { font-size: 1.2rem; }
.post-content h4, .post-content h5, .post-content h6 { font-size: 1rem; }
.post-content ul, .post-content ol { padding-left: 1.3em; }
.post-content li + li { margin-top: 0.25em; }
.post-content blockquote {
  border-left: 8px solid var(--color-border); padding: 0.3em 0 0.3em 1em; font-weight: 700;
}
.post-content img { max-width: 100%; height: auto; display: block; border: 4px solid var(--color-border); }
.post-content hr { border: 0; border-top: 4px solid var(--color-border); margin: 2em 0; }
.post-content dt { font-weight: 800; text-transform: uppercase; }
.post-content dd { margin-left: 1.3em; }

code { font-family: var(--font-mono); font-size: 0.88em; background: var(--color-accent); padding: 0.05em 0.3em; }
pre {
  background: var(--color-code-bg); border: 3px solid var(--color-border);
  padding: 1em; overflow-x: auto;
}
pre code { background: none; padding: 0; font-size: 0.85em; }

.post-content table {
  display: block; width: fit-content; max-width: 100%;
  overflow-x: auto; border-collapse: collapse;
  font-size: 0.93em;
}
.post-content th, .post-content td { border: 2px solid var(--color-border); padding: 0.4em 0.7em; text-align: left; }
.post-content th { background: var(--color-text); color: var(--color-bg); text-transform: uppercase; }

/* --- Riquadri e note ----------------------------------------------------- */
.callout { border: 4px solid var(--color-border); padding: 1em; }
.callout > * + * { margin-top: 0.6em; }
.callout-title { font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; }
/* Le varianti cambiano il bordo o il fondo, mai il colore del testo. */
.callout-note .callout-title { background: var(--color-accent); display: inline-block; padding: 0 0.4em; }
.callout-warning { border-color: #b35c00; }
.callout-danger { border-color: #cc0000; }

.footnotes { margin-top: calc(var(--spacing) * 2); font-size: 0.88rem; }
.footnotes hr { border: 0; border-top: 4px solid var(--color-border); margin-bottom: 1em; }
.footnotes li + li { margin-top: 0.4em; }
.footnote-backref { text-decoration: none; font-weight: 800; margin-left: 0.3em; }

/* --- Tag, voti, navigazione ---------------------------------------------- */
.post-tags, .tag-cloud { list-style: none; padding: 0; margin: 0; display: flex; flex-wrap: wrap; gap: 0.5rem; }
.post-tags { margin-top: calc(var(--spacing) * 1.5); }
.post-tags a, .tag-cloud a {
  display: inline-block; border: 2px solid var(--color-border); padding: 0.1em 0.6em;
  color: var(--color-text); text-decoration: none;
  font-size: 0.85rem; font-weight: 700; text-transform: uppercase;
}
.post-tags a:hover, .tag-cloud a:hover { background: var(--color-accent); }

.upvote { margin-top: calc(var(--spacing) * 1.5); }
.upvote-button {
  font: inherit; font-weight: 800; text-transform: uppercase; cursor: pointer;
  padding: 0.5em 1.2em; color: var(--color-text); background: var(--color-bg);
  border: 4px solid var(--color-border); box-shadow: 5px 5px 0 var(--color-border);
}
.upvote-button:hover { background: var(--color-accent); }
.upvote-button:active { box-shadow: none; translate: 5px 5px; }
.upvote-icon { font-size: 1.1em; }
.upvote-count { font-variant-numeric: tabular-nums; }
.upvote-button[aria-pressed="true"] { border-color: var(--color-accent); color: var(--color-accent); }

.post-nav {
  display: flex; justify-content: space-between; gap: 1rem;
  margin-top: calc(var(--spacing) * 2); padding-top: var(--spacing);
  border-top: 4px solid var(--color-border);
  font-size: 0.9rem; font-weight: 700; text-transform: uppercase;
}
.post-nav .next { margin-left: auto; text-align: right; }

/* --- Elenchi ------------------------------------------------------------- */
.post-list { list-style: none; padding: 0; margin: 0; }
.post-list li { border-bottom: 2px solid var(--color-border); padding: 0.7rem 0; }
.post-list time { font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; margin-right: 0.7rem; }
.post-list a { font-weight: 700; }
.post-list-description { margin-top: 0.25rem; color: var(--color-muted); font-size: 0.9rem; }

/* --- Iscrizione ---------------------------------------------------------- */
.subscribe-form { display: flex; flex-wrap: wrap; gap: 0.6rem; margin-top: var(--spacing); }
.subscribe-form label { flex: 1 0 100%; font-weight: 800; text-transform: uppercase; font-size: 0.85rem; }
.subscribe-form input {
  font: inherit; flex: 1 1 16rem; min-width: 0; padding: 0.5em 0.7em;
  color: var(--color-text); background: var(--color-bg);
  border: 3px solid var(--color-border);
}
.subscribe-form button {
  font: inherit; font-weight: 800; text-transform: uppercase; cursor: pointer;
  padding: 0.5em 1.3em; color: var(--color-text); background: var(--color-accent);
  border: 3px solid var(--color-border);
}
.subscribe-form button:hover { background: var(--color-text); color: var(--color-bg); }

/* --- Paginazione e piede ------------------------------------------------- */
.pagination {
  display: flex; align-items: center; justify-content: space-between; gap: 1rem;
  margin-top: calc(var(--spacing) * 2);
  font-size: 0.9rem; font-weight: 700; text-transform: uppercase;
}
.pagination-info { color: var(--color-muted); font-variant-numeric: tabular-nums; }
.pagination [rel="next"] { margin-left: auto; }

.site-footer {
  margin-top: calc(var(--spacing) * 3); padding-top: var(--spacing);
  border-top: 4px solid var(--color-border);
  font-size: 0.85rem; font-weight: 700; text-transform: uppercase;
}

@media (max-width: 34rem) {
  :root { --spacing: 1rem; }
  .site-title { font-size: 1.5rem; }
  .post-title { font-size: 1.7rem; }
  .post-nav, .pagination { flex-direction: column; align-items: flex-start; }
  .post-nav .next, .pagination [rel="next"] { margin-left: 0; text-align: left; }
}
CSS,
    ],

    'compatto' => [
        'title'       => 'Compatto',
        'description' => 'Densità alta e interlinea ridotta, per chi pubblica spesso.',
        'sort_order'  => 60,
        'css'         => <<<'CSS'
/* ==========================================================================
   Compatto — molte righe sopra la piega, poco spazio sprecato.
   ========================================================================== */

:root {
  --width: 50rem;
  --font-main: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
  --font-mono: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace;
  --font-size: 0.9375rem;
  --line-height: 1.45;
  --color-bg: #ffffff;
  --color-text: #202124;
  --color-muted: #5f6368;
  --color-link: #0b57d0;
  --color-border: #dadce0;
  --color-code-bg: #f1f3f4;
  --color-accent: #0b57d0;
  --spacing: 1rem;
}

@media (prefers-color-scheme: dark) {
  :root {
    --color-bg: #16181c;
    --color-text: #e3e3e3;
    --color-muted: #9aa0a6;
    --color-link: #8ab4f8;
    --color-border: #2e3134;
    --color-code-bg: #1f2124;
    --color-accent: #8ab4f8;
  }
}

/* --- Reset minimo -------------------------------------------------------- */
*, *::before, *::after { box-sizing: border-box; }
body, h1, h2, h3, h4, h5, h6, p, ul, ol, dl, dd, figure, blockquote, pre { margin: 0; }
html { -webkit-text-size-adjust: 100%; }

/* --- Impianto ------------------------------------------------------------ */
body {
  max-width: var(--width); margin: 0 auto;
  padding: var(--spacing) var(--spacing) calc(var(--spacing) * 2.5);
  background: var(--color-bg); color: var(--color-text);
  font: var(--font-size)/var(--line-height) var(--font-main);
  overflow-wrap: break-word; /* un URL lunghissimo non deve sfondare la colonna */
}

/* Stacco fra i blocchi di primo livello: elenco, nuvola di tag, form, paginazione. */
main > * + * { margin-top: var(--spacing); }

a { color: var(--color-link); text-underline-offset: 0.12em; }
:focus-visible { outline: 2px solid var(--color-accent); outline-offset: 1px; border-radius: 2px; }

/* --- Testata: titolo e menu sulla stessa riga finché c'è spazio ---------- */
.site-header {
  display: flex; flex-wrap: wrap; align-items: baseline; gap: 0.3rem 1rem;
  margin-bottom: calc(var(--spacing) * 1.5); padding-bottom: 0.6rem;
  border-bottom: 1px solid var(--color-border);
}
.site-title { font-size: 1.05rem; font-weight: 700; }
.site-title a { color: var(--color-text); text-decoration: none; }
.site-title a:hover { color: var(--color-link); }
.site-nav { display: flex; flex-wrap: wrap; gap: 0.25rem 0.8rem; font-size: 0.85rem; margin-left: auto; }
.site-nav a { color: var(--color-muted); text-decoration: none; }
.site-nav a:hover { color: var(--color-link); text-decoration: underline; }

/* --- Post ---------------------------------------------------------------- */
.post-title { font-size: 1.5rem; line-height: 1.2; font-weight: 700; }
.post-meta { margin-top: 0.2rem; color: var(--color-muted); font-size: 0.8rem; }

.post-content { margin-top: 0.9rem; }
.post-content > * + * { margin-top: 0.7em; }
.post-content h2, .post-content h3, .post-content h4,
.post-content h5, .post-content h6 { margin-top: 1.4em; line-height: 1.25; font-weight: 700; }
.post-content h2 { font-size: 1.2rem; }
.post-content h3 { font-size: 1.05rem; }
.post-content h4, .post-content h5, .post-content h6 { font-size: 0.95rem; }
.post-content ul, .post-content ol { padding-left: 1.2em; }
.post-content li + li { margin-top: 0.15em; }
.post-content blockquote {
  border-left: 3px solid var(--color-border);
  padding-left: 0.8em; color: var(--color-muted);
}
.post-content img { max-width: 100%; height: auto; display: block; border-radius: 4px; }
.post-content hr { border: 0; border-top: 1px solid var(--color-border); margin: 1.4em 0; }
.post-content dt { font-weight: 700; }
.post-content dd { margin-left: 1.2em; }

code {
  font-family: var(--font-mono); font-size: 0.9em;
  background: var(--color-code-bg); padding: 0.1em 0.3em; border-radius: 3px;
}
pre {
  background: var(--color-code-bg); padding: 0.7em 0.8em; border-radius: 4px;
  overflow-x: auto; line-height: 1.4;
}
pre code { background: none; padding: 0; font-size: 0.86em; }

.post-content table {
  display: block; width: fit-content; max-width: 100%;
  overflow-x: auto; border-collapse: collapse;
  font-size: 0.9em;
}
.post-content th, .post-content td { border: 1px solid var(--color-border); padding: 0.25em 0.55em; text-align: left; }
.post-content th { background: var(--color-code-bg); }

/* --- Riquadri e note ----------------------------------------------------- */
.callout {
  border-left: 3px solid var(--color-accent); background: var(--color-code-bg);
  padding: 0.55em 0.8em; border-radius: 0 4px 4px 0; font-size: 0.95em;
}
.callout > * + * { margin-top: 0.4em; }
.callout-title { font-weight: 700; }
.callout-warning { border-left-color: #a35a00; }
.callout-danger { border-left-color: #b3261e; }

.footnotes { margin-top: calc(var(--spacing) * 1.5); font-size: 0.82rem; color: var(--color-muted); }
.footnotes hr { border: 0; border-top: 1px solid var(--color-border); margin-bottom: 0.7em; }
.footnotes li + li { margin-top: 0.2em; }
.footnote-backref { text-decoration: none; margin-left: 0.3em; }

/* --- Tag, voti, navigazione ---------------------------------------------- */
.post-tags, .tag-cloud {
  list-style: none; padding: 0; margin: 0;
  display: flex; flex-wrap: wrap; gap: 0.3rem; font-size: 0.78rem;
}
.post-tags { margin-top: var(--spacing); }
.post-tags a, .tag-cloud a {
  display: inline-block; padding: 0.05em 0.5em; border-radius: 3px;
  background: var(--color-code-bg); color: var(--color-muted); text-decoration: none;
}
.post-tags a:hover, .tag-cloud a:hover { color: var(--color-link); }

.upvote { margin-top: var(--spacing); }
.upvote-button {
  font: inherit; font-size: 0.85rem; cursor: pointer; padding: 0.25em 0.7em;
  color: var(--color-text); background: var(--color-code-bg);
  border: 1px solid var(--color-border); border-radius: 4px;
}
.upvote-button:hover { border-color: var(--color-accent); color: var(--color-accent); }
.upvote-icon { color: var(--color-accent); }
.upvote-count { font-variant-numeric: tabular-nums; }
.upvote-button[aria-pressed="true"] { border-color: var(--color-accent); color: var(--color-accent); }

.post-nav {
  display: flex; justify-content: space-between; gap: 0.8rem;
  margin-top: calc(var(--spacing) * 1.5); padding-top: 0.7rem;
  border-top: 1px solid var(--color-border);
  font-size: 0.85rem;
}
.post-nav .next { margin-left: auto; text-align: right; }

/* --- Elenchi: data e titolo incolonnati ---------------------------------- */
.post-list { list-style: none; padding: 0; margin: 0; }
.post-list li { display: grid; grid-template-columns: 6.5rem 1fr; gap: 0 0.6rem; padding: 0.2rem 0; }
.post-list time { color: var(--color-muted); font-size: 0.8rem; font-variant-numeric: tabular-nums; }
.post-list a { text-decoration: none; }
.post-list a:hover { text-decoration: underline; }
.post-list-description { grid-column: 2; margin-top: 0; color: var(--color-muted); font-size: 0.82rem; }

/* --- Iscrizione ---------------------------------------------------------- */
.subscribe-form {
  display: flex; flex-wrap: wrap; align-items: center; gap: 0.4rem;
  margin-top: var(--spacing);
}
.subscribe-form label { flex: 1 0 100%; font-size: 0.8rem; color: var(--color-muted); }
.subscribe-form input {
  font: inherit; flex: 1 1 14rem; min-width: 0; padding: 0.3em 0.55em;
  color: var(--color-text); background: var(--color-bg);
  border: 1px solid var(--color-border); border-radius: 4px;
}
.subscribe-form button {
  font: inherit; cursor: pointer; padding: 0.3em 0.9em;
  color: var(--color-bg); background: var(--color-accent);
  border: 1px solid var(--color-accent); border-radius: 4px;
}

/* --- Paginazione e piede ------------------------------------------------- */
.pagination {
  display: flex; align-items: center; justify-content: space-between; gap: 0.8rem;
  margin-top: calc(var(--spacing) * 1.5);
  font-size: 0.85rem;
}
.pagination-info { color: var(--color-muted); font-variant-numeric: tabular-nums; }
.pagination [rel="next"] { margin-left: auto; }

.site-footer {
  margin-top: calc(var(--spacing) * 2); padding-top: 0.7rem;
  border-top: 1px solid var(--color-border);
  color: var(--color-muted); font-size: 0.78rem;
}

@media (max-width: 34rem) {
  .site-nav { margin-left: 0; }
  /* Sotto i 34rem la colonna della data toglie troppo spazio al titolo. */
  .post-list li { display: block; padding: 0.35rem 0; }
  .post-list time { margin-right: 0.5rem; }
  .post-nav, .pagination { flex-direction: column; align-items: flex-start; }
  .post-nav .next, .pagination [rel="next"] { margin-left: 0; text-align: left; }
}
CSS,
    ],

    'terminale' => [
        'title'       => 'Terminale',
        'description' => 'Fosfori verdi su nero, cursore lampeggiante, ma ancora leggibile.',
        'sort_order'  => 70,
        'css'         => <<<'CSS'
/* ==========================================================================
   Terminale — verde su nero. L'estetica CRT non deve costare leggibilità:
   il verde del testo sta a 14:1 sul fondo e i link virano al ciano, così si
   distinguono anche senza affidarsi al solo sottolineato.
   ========================================================================== */

:root {
  color-scheme: dark;
  --width: 46rem;
  --font-main: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, "DejaVu Sans Mono", "Liberation Mono", monospace;
  --font-mono: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, "DejaVu Sans Mono", "Liberation Mono", monospace;
  --font-size: 0.9375rem;
  --line-height: 1.6;
  --color-bg: #06120a;
  --color-text: #33ff33;
  --color-muted: #57a86a;
  --color-link: #7df9ff;
  --color-border: #1d4527;
  --color-code-bg: #0b1f11;
  --color-accent: #ffb000;
  --spacing: 1.4rem;
}

/* --- Reset minimo -------------------------------------------------------- */
*, *::before, *::after { box-sizing: border-box; }
body, h1, h2, h3, h4, h5, h6, p, ul, ol, dl, dd, figure, blockquote, pre { margin: 0; }
html { -webkit-text-size-adjust: 100%; background: #06120a; }

/* --- Impianto ------------------------------------------------------------ */
body {
  max-width: var(--width); margin: 0 auto;
  padding: calc(var(--spacing) * 1.5) var(--spacing) calc(var(--spacing) * 3);
  background: var(--color-bg); color: var(--color-text);
  font: var(--font-size)/var(--line-height) var(--font-main);
  overflow-wrap: break-word; /* un URL lunghissimo non deve sfondare la colonna */
}

/* Stacco fra i blocchi di primo livello: elenco, nuvola di tag, form, paginazione. */
main > * + * { margin-top: var(--spacing); }

a { color: var(--color-link); text-underline-offset: 0.2em; }
a:hover { background: var(--color-link); color: var(--color-bg); text-decoration: none; }
:focus-visible { outline: 2px solid var(--color-accent); outline-offset: 2px; }

/* --- Testata ------------------------------------------------------------- */
.site-header {
  margin-bottom: calc(var(--spacing) * 1.5); padding-bottom: var(--spacing);
  border-bottom: 1px solid var(--color-border);
}
.site-title { font-size: 1.15rem; font-weight: 700; }
.site-title a { color: var(--color-text); text-decoration: none; }
.site-title a::before { content: "~/"; color: var(--color-muted); }
/* Cursore lampeggiante: puro ornamento, generato da ::after per non finire
   nel testo copiato né nella lettura assistita del titolo. */
.site-title::after {
  content: "_"; margin-left: 0.2ch; color: var(--color-accent);
  animation: noblogs-blink 1.1s step-end infinite;
}
@keyframes noblogs-blink { 0%, 100% { opacity: 1; } 50% { opacity: 0; } }
@media (prefers-reduced-motion: reduce) {
  .site-title::after { animation: none; }
}

.site-nav { margin-top: 0.6rem; display: flex; flex-wrap: wrap; gap: 0.3rem 1.2ch; font-size: 0.875rem; }
.site-nav a { color: var(--color-muted); text-decoration: none; }
.site-nav a::before { content: "> "; }
.site-nav a:hover { color: var(--color-bg); background: var(--color-muted); }

/* --- Post ---------------------------------------------------------------- */
.post-title { font-size: 1.4rem; line-height: 1.25; font-weight: 700; }
.post-meta { margin-top: 0.4rem; color: var(--color-muted); font-size: 0.85rem; }

.post-content { margin-top: var(--spacing); }
.post-content > * + * { margin-top: 1em; }
.post-content h2, .post-content h3, .post-content h4,
.post-content h5, .post-content h6 { margin-top: 2em; font-size: 1rem; line-height: 1.3; color: var(--color-accent); }
.post-content h2 { font-size: 1.1rem; }
.post-content h2::before { content: "## "; opacity: 0.6; }
.post-content h3::before { content: "### "; opacity: 0.6; }
.post-content ul, .post-content ol { padding-left: 2ch; }
.post-content ul { list-style: none; }
.post-content ul > li::before { content: "* "; color: var(--color-accent); margin-left: -2ch; }
.post-content li + li { margin-top: 0.25em; }
.post-content li::marker { color: var(--color-accent); }
.post-content blockquote {
  border-left: 2px solid var(--color-muted);
  padding-left: 1ch; color: var(--color-muted);
}
.post-content img { max-width: 100%; height: auto; display: block; border: 1px solid var(--color-border); }
.post-content hr { border: 0; border-top: 1px solid var(--color-border); margin: 2em 0; }
.post-content dt { color: var(--color-accent); }
.post-content dd { margin-left: 2ch; }

/* --font-mono coincide con --font-main, ma resta esplicito: chi personalizza
   può cambiare il solo carattere del codice senza toccare il resto. */
code { font-family: var(--font-mono); background: var(--color-code-bg); padding: 0 0.3ch; }
pre {
  font-family: var(--font-mono); background: var(--color-code-bg);
  border: 1px solid var(--color-border); padding: 0.8em 1ch;
  overflow-x: auto; font-size: 0.95em;
}
pre code { background: none; padding: 0; }

.post-content table {
  display: block; width: fit-content; max-width: 100%;
  overflow-x: auto; border-collapse: collapse;
  font-size: 0.95em;
}
.post-content th, .post-content td { border: 1px solid var(--color-border); padding: 0.25em 1ch; text-align: left; }
.post-content th { color: var(--color-accent); }

/* --- Riquadri e note ----------------------------------------------------- */
.callout { border: 1px solid var(--color-border); background: var(--color-code-bg); padding: 0.8em 1ch; }
.callout > * + * { margin-top: 0.5em; }
.callout-title { color: var(--color-accent); text-transform: uppercase; letter-spacing: 0.06em; }
.callout-title::before { content: "[!] "; }
.callout-warning { border-color: var(--color-accent); }
.callout-danger { border-color: #ff6b6b; }
.callout-danger .callout-title { color: #ff6b6b; }

.footnotes { margin-top: calc(var(--spacing) * 2); font-size: 0.85rem; color: var(--color-muted); }
.footnotes hr { border: 0; border-top: 1px solid var(--color-border); margin-bottom: 1em; }
.footnotes ol { padding-left: 3ch; }
.footnote-backref { text-decoration: none; margin-left: 1ch; color: var(--color-accent); }

/* --- Tag, voti, navigazione ---------------------------------------------- */
.post-tags, .tag-cloud {
  list-style: none; padding: 0; margin: 0;
  display: flex; flex-wrap: wrap; gap: 0.3rem 1.5ch; font-size: 0.85rem;
}
.post-tags { margin-top: calc(var(--spacing) * 1.5); }
.post-tags a, .tag-cloud a { color: var(--color-muted); text-decoration: none; }
.post-tags a::before, .tag-cloud a::before { content: "#"; color: var(--color-accent); }
.post-tags a:hover, .tag-cloud a:hover { color: var(--color-bg); background: var(--color-muted); }

.upvote { margin-top: calc(var(--spacing) * 1.5); }
.upvote-button {
  font: inherit; cursor: pointer; padding: 0.35em 1.2ch;
  color: var(--color-text); background: transparent;
  border: 1px solid var(--color-border);
}
.upvote-button:hover { border-color: var(--color-accent); color: var(--color-accent); }
.upvote-icon { color: var(--color-accent); }
.upvote-count { font-variant-numeric: tabular-nums; }
.upvote-button[aria-pressed="true"] { border-color: var(--color-accent); color: var(--color-accent); }

.post-nav {
  display: flex; justify-content: space-between; gap: 1rem;
  margin-top: calc(var(--spacing) * 2); padding-top: var(--spacing);
  border-top: 1px solid var(--color-border);
  font-size: 0.875rem;
}
.post-nav .next { margin-left: auto; text-align: right; }

/* --- Elenchi ------------------------------------------------------------- */
.post-list { list-style: none; padding: 0; margin: 0; }
.post-list li + li { margin-top: 0.8rem; }
.post-list time { color: var(--color-muted); margin-right: 1ch; font-variant-numeric: tabular-nums; }
.post-list a { text-decoration: none; }
.post-list a:hover { text-decoration: underline; }
.post-list-description { margin-top: 0.15rem; color: var(--color-muted); font-size: 0.85rem; }

/* --- Iscrizione ---------------------------------------------------------- */
.subscribe-form { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: var(--spacing); }
.subscribe-form label { flex: 1 0 100%; color: var(--color-muted); font-size: 0.85rem; }
.subscribe-form input {
  font: inherit; flex: 1 1 16rem; min-width: 0; padding: 0.4em 1ch;
  color: var(--color-text); background: var(--color-code-bg);
  border: 1px solid var(--color-border);
}
.subscribe-form input:focus-visible { border-color: var(--color-accent); }
.subscribe-form button {
  font: inherit; cursor: pointer; padding: 0.4em 2ch;
  color: var(--color-bg); background: var(--color-text);
  border: 1px solid var(--color-text);
}
.subscribe-form button:hover { background: var(--color-accent); border-color: var(--color-accent); }

/* --- Paginazione e piede ------------------------------------------------- */
.pagination {
  display: flex; align-items: center; justify-content: space-between; gap: 1rem;
  margin-top: calc(var(--spacing) * 2);
  font-size: 0.875rem;
}
.pagination-info { color: var(--color-muted); font-variant-numeric: tabular-nums; }
.pagination [rel="next"] { margin-left: auto; }

.site-footer {
  margin-top: calc(var(--spacing) * 3); padding-top: var(--spacing);
  border-top: 1px solid var(--color-border);
  color: var(--color-muted); font-size: 0.8125rem;
}

@media (max-width: 34rem) {
  :root { --spacing: 1rem; }
  .post-nav, .pagination { flex-direction: column; align-items: flex-start; }
  .post-nav .next, .pagination [rel="next"] { margin-left: 0; text-align: left; }
}
CSS,
    ],

];
