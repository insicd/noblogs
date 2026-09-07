# Sviluppo

Noblogs è PHP 8.1 senza Composer, senza Node, senza framework. Il codice
sta in `app/`, con un autoload PSR-4 minimale definito in
`app/bootstrap.php`: `Noblogs\Models\Blog` è `app/Models/Blog.php`.

Questa pagina serve a chi vuole aggiungere un tema, una lingua, o capire
dove mettere le mani senza rompere il resto.

## Sommario

- [Mappa](#mappa)
- [Due router](#due-router)
- [Aggiungere un tema](#aggiungere-un-tema)
- [Aggiungere una lingua](#aggiungere-una-lingua)
- [Markdown](#markdown)
- [Convenzioni](#convenzioni)

## Mappa

```
app/
  bootstrap.php     autoload, config, fuso UTC, lingua
  routes.php        tabella delle rotte (piattaforma / blog)
  Core/             Request, Response, Router, Config, Session, Auth, Url, Tenant
  Models/           accesso al database (un file, una tabella)
  Controllers/      Auth, Admin, Dashboard, Platform, Site
  Markdown/         parser, direttive, bonifica, cache
  Views/            template PHP
  Support/          stringhe, date, helper
lang/<codice>/      cataloghi di traduzione
db/schema.sql       tabelle
db/themes.php       galleria dei temi
public/             unica cartella esposta al web
storage/            cache, log, sessioni, file temporanei
```

`public/index.php` è l'unico punto di ingresso. Risolve il tenant, apre la
sessione solo se siamo sulla piattaforma, e passa la richiesta al router
giusto.

## Due router

Lo stesso percorso significa due cose diverse a seconda dell'host.

- `esempio.tld/dashboard` è il pannello.
- `miablog.esempio.tld/dashboard` è un articolo (o una 404) di quel blog.

`Tenant::resolve()` decide. Ordine: host principale → sottodominio →
dominio personalizzato →, se abilitato, primo segmento del path. I
segmenti riservati (`accedi`, `dashboard`, `admin`, `esplora`, …) non
possono coincidere col nome di un blog.

`Url` è l'unico posto che conosce la differenza tra sottodominio, percorso
e dominio proprio. Nei template si usa `Url::site()`, `Url::platform()`,
`Url::blogRoot($blog)`: non si concatenano host a mano.

## Aggiungere un tema

I temi vivono in `db/themes.php`, un array `slug => [title, description,
sort_order, css]`. Il CSS finisce **inline** nella pagina del blog, quindi
niente `@import` e nessuna richiesta a un CDN: romperebbe la promessa di
non chiamare domini terzi.

Tutti i temi espongono le stesse proprietà in `:root`:

```css
:root {
  --width: 46rem;
  --font-main: ...;
  --font-mono: ...;
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
```

Chi scrive CSS personalizzato può cambiarne una sola senza riscrivere il
foglio. Lo schema scuro sta in `@media (prefers-color-scheme: dark)` e
ridefinisce gli stessi nomi.

Classi sul `body` che gli autori usano nei fogli personalizzati:

- `.home` homepage
- `.post` / `.page` articolo o pagina
- `.blog` elenco
- `.subscribe` iscrizione
- `.not-found` 404

Dopo aver modificato `db/themes.php`:

```sh
php bin/noblogs sincronizza-temi
php bin/noblogs svuota-cache
```

`Theme::sync()` inserisce i temi nuovi, aggiorna titolo e CSS di quelli
già in database, **non cancella** uno slug che hai tolto dal file: un blog
potrebbe ancora usarlo. Per ritirare un tema, spostane prima i blog su un
altro slug.

Un `</style>` nel CSS viene troncato: chiuderebbe il blocco inline e
permetterebbe di iniettare markup.

## Aggiungere una lingua

Le traduzioni stanno in `lang/<codice>/*.php` e restituiscono un array
chiave => testo. I file si uniscono in un catalogo piatto: le chiavi sono
già prefissate per area (`auth.login.title`, `post.form.slug`), quindi non
si pestano.

Per aggiungere il francese:

1. Copia `lang/it/` in `lang/fr/`.
2. Traduci i valori, non le chiavi.
3. I `:segnaposto` restano com'è: `Pagina :current di :total`.
4. `I18n::available()` elenca le cartelle; la nuova lingua compare nei
   menu senza altri registri.

Una chiave mancante ricade sull'italiano, poi sulla chiave stessa. Si può
tradurre a pezzi.

La lingua di un **blog** (`blogs.lang`) governa le etichette pubbliche di
quel blog (cerca, iscriviti, 404). La lingua della **piattaforma**
(`site.locale`, o quella dell'account nel pannello) governa dashboard e
amministrazione. Le pagine lunghe della piattaforma (informativa, termini,
guida al Markdown) sono prosa in italiano dentro le viste: non passano dal
catalogo.

## Markdown

Il parser è in `app/Markdown/`, non è CommonMark puro. Scelte volute:

- niente blocchi di codice indentati a 4 spazi (in pratica sono liste
  malriuscite);
- le direttive `{{ ... }}` attraversano il parser e le espande
  `Directives.php` dopo la bonifica;
- HTML grezzo e fence di codice finiscono in segnaposto opachi, così
  nessuna passata successiva li tocca.

Intestazione dei file, per import ed export:

```
---
titolo: Un articolo
tag: appunti, lavoro
---

Testo.

title: I like Bears
link: i-like-bears
___

Testo.
```

La prima forma è Jekyll/Hugo; la seconda è quella di Bear Blog. La
dashboard non la chiede: i metadati stanno in campi. Serve a chi importa
un archivio.

Per una nuova direttiva si aggiunge un nome in `Directives.php` e, se è un
blocco, alla lista `BLOCK`. Poi una riga nella guida `/aiuto/markdown`,
che rende gli esempi con lo stesso parser degli articoli.

La cache del Markdown sta in `storage/cache/`. Si svuota con
`php bin/noblogs svuota-cache` o dalla manutenzione.

## Convenzioni

- PHP con tipi dichiarati, `declare(strict_types=1)`.
- Niente dipendenze esterne. Se serve una libreria, la ragione deve stare
  in un commento e deve funzionare ancora su un hosting condiviso.
- I blog pubblici restano leggibili senza JavaScript. `hit.js`, `upvote.js`
  e `dates.js` sono un aiuto, non un requisito; `editor.js` vive solo nel
  pannello e la textarea funziona anche se lo script non parte.
- Le stringhe visibili passano da `__('chiave')`. Non si concatenano frasi
  tradotte.
- Gli URL si costruiscono con `Url::*`.
- I timestamp in database sono UTC. Si convertono in uscita con
  `Dates::format()` e il fuso dell'autore.
- Uno schema nuovo si documenta nelle note di rilascio e si applica a
  mano: Noblogs non migra il database da solo.

Dopo una modifica ai temi o al parser, svuota la cache. Dopo una modifica
a `db/schema.sql`, scrivi nel changelog come aggiornare un'installazione
già viva: `ALTER TABLE`, non «rilancia l'installer».
