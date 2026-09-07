# Installazione

Questa guida accompagna l'installazione passo per passo. Se hai un VPS e sai
già cosa stai facendo, il riassunto nel [README](../README.md) basta.

## Sommario

- [Prima di cominciare](#prima-di-cominciare)
- [1. Il database](#1-il-database)
- [2. I file](#2-i-file)
- [3. I permessi](#3-i-permessi)
- [4. L'installer](#4-linstaller)
- [5. Dopo l'installazione](#5-dopo-linstallazione)
- [Pannelli di hosting](#pannelli-di-hosting)
  - [cPanel](#cpanel)
  - [Plesk](#plesk)
  - [DirectAdmin](#directadmin)
- [Installazione senza installer](#installazione-senza-installer)
- [Quando la document root non si può spostare](#quando-la-document-root-non-si-può-spostare)
- [Problemi frequenti](#problemi-frequenti)

## Prima di cominciare

Serve:

- un dominio;
- un hosting con PHP 8.1 o superiore e MySQL 5.7 / MariaDB 10.3 o superiori;
- la possibilità di creare un database;
- l'accesso FTP o SFTP, oppure SSH.

Verifica la versione di PHP dal pannello dell'hosting, oppure caricando un
file `prova.php` con dentro `<?php phpinfo();` — ricordandosi di cancellarlo
subito dopo, perché rivela molto della configurazione del server.

Se vuoi i blog sui sottodomini (`miablog.esempio.tld`), controlla anche che
l'hosting permetta i **sottodomini jolly**. Non è scontato sui piani più
economici; in quel caso si usa la modalità percorso, che non richiede nulla.

## 1. Il database

Crea un database vuoto con codifica `utf8mb4` e collazione
`utf8mb4_unicode_ci`, e un utente con tutti i permessi su quel database.
Prendi nota di:

- **host** — quasi sempre `localhost`. Alcuni hosting usano un nome diverso
  (`mysql.tuohost.it`) o un indirizzo IP: è scritto nel pannello.
- **porta** — 3306, se non c'è scritto altro.
- **nome del database** — spesso il pannello ci mette davanti il tuo nome
  utente: `utente_noblogs`.
- **utente** e **password**.

Il **prefisso delle tabelle** serve solo se nello stesso database vive già
un'altra applicazione. Se il database è dedicato a Noblogs lascialo vuoto: gli
indici sono più corti e le query più leggibili. Se lo usi, scrivilo con il
trattino basso finale (`nb_`).

## 2. I file

Scarica l'archivio del progetto e scompattalo sul tuo computer. Ottieni
questa struttura:

```
noblogs/
├── app/          codice
├── bin/          strumenti da riga di comando
├── config/       configurazione
├── db/           schema del database, temi
├── docs/         documentazione
├── install/      installer (da cancellare dopo)
├── lang/         traduzioni
├── public/       ← la sola cartella che va esposta al web
└── storage/      cache, registri
```

Il punto importante è questo: **solo `public/` deve essere raggiungibile dal
web.** Tutto il resto contiene codice, la configurazione con le credenziali
del database e i registri: se sono pubblici, sono un problema.

Hai due strade.

**Strada A — la document root si può spostare** (la migliore). Carichi tutto
il progetto in una cartella qualsiasi fuori dalla parte pubblica, per esempio
`/home/utente/noblogs/`, e imposti la cartella pubblica del dominio su
`/home/utente/noblogs/public`. Come si fa dipende dal pannello: vedi sotto.

**Strada B — la document root è fissa.** Carichi la cartella `noblogs/`
dentro `public_html/`. Il file `.htaccess` nella radice del progetto nega
l'accesso a tutto e `public/.htaccess` lo riapre solo per la cartella
pubblica; il sito risponderà su `esempio.tld/noblogs/public/`. Per averlo
sulla radice del dominio vedi
[Quando la document root non si può spostare](#quando-la-document-root-non-si-può-spostare).

Carica i file in modalità **binaria** (o «automatica»): il client FTP non
deve convertire le fine di riga, altrimenti gli archivi caricati e le firme
non tornano.

## 3. I permessi

Tre cartelle devono essere scrivibili dal processo che esegue PHP:

| Cartella | Cosa contiene |
|---|---|
| `storage/` | cache del rendering, registro degli errori, file temporanei |
| `public/media/` | i file caricati dagli autori |
| `config/` | serve solo durante l'installazione, per scriverci `config.php` |

Da SSH:

```sh
chmod -R 755 storage public/media config
```

Se non basta (succede quando PHP gira con un utente diverso dal proprietario
dei file):

```sh
chmod -R 775 storage public/media config
```

Su un VPS la soluzione pulita è assegnare quelle cartelle all'utente del
server web invece di allargare i permessi:

```sh
sudo chown -R www-data:www-data storage public/media config
```

Dal gestore file dei pannelli: seleziona la cartella, «Permessi» o
«Change permissions», spunta scrittura per proprietario e gruppo, e attiva
«applica alle sottocartelle».

`config/` può tornare a 555 subito dopo l'installazione: Noblogs non riscrive
mai `config.php` da solo.

## 4. L'installer

Apri il dominio nel browser. Finché `config/config.php` non esiste, ogni
richiesta finisce sull'installer, che si presenta in cinque passi.

**Passo 1 — Requisiti.** Verifica la versione di PHP, le estensioni e la
scrivibilità delle cartelle. Se manca qualcosa di obbligatorio non si va
avanti: la pagina spiega cosa fare e si può ricaricare dopo aver corretto. Le
estensioni facoltative mancanti sono segnalate ma non bloccano.

**Passo 2 — Database.** I dati del passo 1. Il pulsante «Prova la
connessione» si collega davvero al database prima di lasciarti proseguire, e
avvisa se trova già tabelle con quel prefisso (segno che c'è un'altra
installazione, o i resti di una precedente).

**Passo 3 — Piattaforma.** Dominio principale, nome del sito, motto, HTTPS,
modalità di routing, fuso orario, lingua, email mittente e di contatto. La
pagina spiega cosa richiede la modalità sottodominio e cosa non richiede la
modalità percorso.

**Passo 4 — Amministratore.** Email e password del primo account, creato come
amministratore e già verificato. La password deve essere lunga almeno dodici
caratteri: è la chiave di tutta la piattaforma.

**Passo 5 — Esecuzione.** Crea le tabelle, popola gli otto temi, crea
l'amministratore, genera i salt casuali e scrive `config/config.php`.

Se `config/` non è scrivibile — capita spesso — l'installer **mostra il
contenuto del file da copiare a mano**: lo salvi come `config/config.php` con
un editor di testo semplice (niente Word, niente Pages) e lo carichi via FTP.
Il resto dell'installazione è già stato fatto.

L'installer si può ricaricare e ripercorrere: i dati inseriti restano in
sessione. Se `config/config.php` esiste già si rifiuta di partire, per non
sovrascrivere un'installazione viva.

## 5. Dopo l'installazione

**Cancella la cartella `install/`.** Non serve più e non deve restare.

```sh
rm -rf install
```

**Verifica i permessi.** `config/` può tornare in sola lettura (555 o 444 per
`config.php`).

**Configura il cron:**

```cron
0 4 * * * php /percorso/di/noblogs/bin/noblogs manutenzione >/dev/null 2>&1
```

Nei pannelli di hosting c'è una sezione «Cron Jobs» o «Attività
pianificate». Se il comando `php` non viene trovato, va scritto il percorso
completo: lo trovi con `which php` da SSH, oppure è indicato nel pannello
(spesso `/usr/local/bin/php` o `/opt/alt/php82/usr/bin/php`).

**Prova che tutto funzioni:**

```sh
php bin/noblogs statistiche
```

**Entra come amministratore** su `esempio.tld/accedi`, poi vai su
`esempio.tld/admin`. L'area di amministrazione non è linkata dalle pagine
pubbliche: chi non ha i permessi riceve un 404, non un «vietato», così non
scopre nemmeno che esiste.

**Rivedi le impostazioni** in `/admin/impostazioni`: registrazioni aperte o
chiuse, verifica dell'email obbligatoria, revisione dei nuovi blog, limiti
predefiniti.

## Pannelli di hosting

### cPanel

**Creare il database.** *MySQL® Databases* → nome del database → *Create*.
Nella stessa pagina, *Add New User*, poi *Add User To Database* e spunta *ALL
PRIVILEGES*. cPanel aggiunge il tuo nome utente come prefisso: il database
`noblogs` diventa `utente_noblogs`, e lo stesso vale per l'utente.

**Spostare la document root.** *Domains* (nelle versioni recenti) → la riga
del dominio → *Manage* → *Document Root* → scrivi
`/home/utente/noblogs/public`. Nelle versioni più vecchie la si cambia solo
per i sottodomini e per i domini aggiuntivi, non per il principale: in quel
caso conviene creare il sottodominio `blog` puntato su
`/home/utente/noblogs/public`, oppure usare la
[strada B](#quando-la-document-root-non-si-può-spostare).

**Sottodomini jolly.** *Domains* → *Create A New Domain* → come dominio
scrivi `*.esempio.tld` e come document root la stessa cartella `public/` del
dominio principale. Se cPanel rifiuta l'asterisco, l'hosting non permette i
sottodomini jolly: usa `routing.mode = 'path'`.

**Certificato wildcard.** *SSL/TLS Status* con AutoSSL non copre i
sottodomini jolly. Serve un certificato wildcard, che si può caricare in
*SSL/TLS* → *Install and Manage SSL*. Molti hosting condivisi li vendono a
parte.

**Cron.** *Advanced* → *Cron Jobs*. Come comando:

```
/usr/local/bin/php /home/utente/noblogs/bin/noblogs manutenzione
```

**Versione di PHP.** *Software* → *MultiPHP Manager*, oppure *Select PHP
Version* nei pannelli con CloudLinux. Nella stessa pagina si attivano le
estensioni: verifica `pdo_mysql`, `mbstring`, `json`, e possibilmente `gd`,
`dom`, `zip`, `intl`, `fileinfo`.

### Plesk

**Creare il database.** *Siti web e domini* → il dominio → *Database* →
*Aggiungi database*. Plesk crea l'utente nella stessa schermata.

**Spostare la document root.** *Siti web e domini* → il dominio →
*Impostazioni di hosting* → *Radice del documento*: scrivi
`noblogs/public` (il percorso è relativo alla cartella del dominio). È la
strada più comoda: Plesk lo permette anche sul dominio principale.

**Sottodomini jolly.** *Siti web e domini* → *Aggiungi sottodominio* →
nome `*`. Se Plesk non accetta l'asterisco, si può ottenere lo stesso
risultato da *Apache & nginx Settings* aggiungendo alla configurazione
aggiuntiva di Apache:

```apache
ServerAlias *.esempio.tld
```

**Certificato wildcard.** *Certificati SSL/TLS* → *Installa un certificato
Let's Encrypt gratuito* → spunta *Includi un certificato jolly*. Plesk
gestisce la validazione DNS-01 da sé se il DNS è ospitato da lui: è il caso
in cui i sottodomini con HTTPS costano meno fatica.

**Cron.** *Siti web e domini* → *Attività pianificate* → *Aggiungi
attività* → tipo *Esegui uno script PHP*, e come script
`/var/www/vhosts/esempio.tld/noblogs/bin/noblogs`, con `manutenzione` fra gli
argomenti.

**Versione di PHP.** *Impostazioni di hosting* → *Supporto PHP*. Le
estensioni si attivano in *PHP Settings* solo se l'amministratore del server
le ha installate.

### DirectAdmin

**Creare il database.** *Account Manager* → *MySQL Management* → *Create new
Database*. Come in cPanel, il nome viene prefissato con l'utente.

**Spostare la document root.** DirectAdmin non permette di cambiarla dal
pannello. Due possibilità:

1. Creare un sottodominio (*Domain Setup* → il dominio → *Subdomains*) e poi,
   dal gestore file, sostituire la cartella `public_html` del sottodominio con
   un collegamento simbolico a `public/`. Da SSH:

   ```sh
   rm -rf ~/domains/esempio.tld/public_html
   ln -s ~/noblogs/public ~/domains/esempio.tld/public_html
   ```

2. Usare la [strada B](#quando-la-document-root-non-si-può-spostare).

**Sottodomini jolly.** *Domain Setup* → il dominio → *Subdomains* → nome
`*`. Se non viene accettato, serve l'intervento dell'amministratore del
server, che può aggiungere `ServerAlias *.esempio.tld` a un template
personalizzato.

**Cron.** *Advanced Features* → *Cron Jobs*.

**Versione di PHP.** *Domain Setup* → il dominio → *PHP Version Selector*.

## Installazione senza installer

Utile per automatizzare, o quando l'installer non riesce a scrivere niente.

```sh
# 1. Configurazione
cp config/config.sample.php config/config.php
$EDITOR config/config.php
```

Cambia almeno: `db.*`, `site.domain`, `site.email_from`, `site.contact_email`,
e **i due salt** in `security`. I salt vanno generati a caso:

```sh
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
```

Il primo (`analytics_salt`) anonimizza gli indirizzi IP nelle statistiche: se
lo si cambia dopo, la continuità del conteggio dei visitatori unici si
interrompe. Il secondo (`app_key`) firma le sessioni: cambiandolo, tutti
vengono disconnessi.

```sh
# 2. Schema (prefisso vuoto)
mysql -u noblogs -p noblogs < db/schema.sql

# 2-bis. Schema con prefisso «nb_»
sed 's/{{prefix}}/nb_/g' db/schema.sql | mysql -u noblogs -p noblogs

# 3. Temi
php bin/noblogs sincronizza-temi

# 4. Amministratore
php bin/noblogs crea-admin io@esempio.tld

# 5. Controllo
php bin/noblogs statistiche
```

## Quando la document root non si può spostare

Se il dominio deve puntare per forza a `public_html/` e vuoi Noblogs sulla
radice del dominio:

1. Carica il progetto in `public_html/noblogs/`.
2. Sposta il **contenuto** di `public_html/noblogs/public/` in
   `public_html/`: quindi `index.php`, `.htaccess`, `assets/` e `media/`.
3. In `public_html/index.php` correggi il percorso del bootstrap:

   ```php
   require_once __DIR__ . '/noblogs/app/bootstrap.php';
   ```

4. **Tieni** `public_html/noblogs/.htaccess`, quello che nega ogni accesso: le
   direttive di Apache valgono per le richieste HTTP, non per le `require` di
   PHP, quindi il codice resta protetto dal web e utilizzabile
   dall'applicazione. Va cancellato soltanto nel caso in cui il progetto
   finisse direttamente nella document root, che qui non è quello che
   stiamo facendo.
5. Verifica che `esempio.tld/noblogs/config/config.php` risponda 403 e che
   `esempio.tld/` mostri la pagina iniziale.

È una configurazione che funziona ma va rifatta a ogni aggiornamento: se
l'hosting permette di spostare la document root, quella strada costa meno.

## Problemi frequenti

**Errore 500 su tutto, subito dopo il caricamento dei file.** Quasi sempre è
un `.htaccess` che usa direttive non permesse. Guarda il registro degli errori
del server dal pannello. Se contiene `not allowed here`, l'hosting ha
`AllowOverride` limitato: chiedi all'assistenza di attivarlo, oppure togli da
`public/.htaccess` i blocchi `<IfModule mod_headers.c>` e `Options`.

**403 su tutto.** Manca il `Require all granted` in `public/.htaccess`, che
riapre l'accesso negato dal `.htaccess` nella radice del progetto: quel
divieto si eredita nelle sottocartelle. Ricarica il file originale.

**La pagina iniziale funziona ma ogni altro indirizzo dà 404.** La
riscrittura degli URL non è attiva. Su Apache serve mod_rewrite e
`AllowOverride All`; su nginx la direttiva
`try_files $uri /index.php$is_args$args;`. Su IIS serve una regola in
`web.config`, che Noblogs non fornisce.

**«Impossibile connettersi al database».** Controlla che l'host sia quello
indicato dal pannello e non `localhost`, e che il nome del database e
dell'utente comprendano il prefisso aggiunto dal pannello. Se l'hosting usa un
socket non standard, va indicato in `db.socket`.

**I sottodomini danno un errore di certificato.** Il certificato non è
wildcard. Fino a quando non lo è, usa `routing.mode = 'path'` oppure
`site.https = false` in prova.

**Un blog appena creato dà 404 sul sottodominio.** Manca il DNS wildcard o il
vhost wildcard. Con `routing.path_fallback = true` il blog resta comunque
raggiungibile su `esempio.tld/miablog/`, ed è un buon modo per capire se il
problema è nel DNS o altrove.

**«La cartella non è scrivibile» ma i permessi sembrano giusti.** Su alcuni
hosting PHP gira con un utente diverso dal proprietario dei file. Prova 775, e
se non basta 777 solo su `storage/` e `public/media/` (non su `config/`, dove
serve la scrittura solo per un momento).

**Le email non arrivano.** Il driver `mail` usa la funzione `mail()` di PHP,
che molti hosting limitano o filtrano come spam. Passa a `mail.driver = 'smtp'`
con le credenziali di una casella del tuo dominio: gli indirizzi mittenti che
coincidono col dominio passano molto più facilmente i filtri. Per capire cosa
sta uscendo, `mail.driver = 'log'` scrive i messaggi in
`storage/logs/mail.log` senza inviarli.

**Errori senza spiegazione.** Attiva `'debug' => true` in `config/config.php`
per vedere il dettaglio a schermo, guarda `storage/logs/error.log` e
**rimetti `false`** quando hai finito: con `debug` attivo le pagine di errore
mostrano percorsi e frammenti di codice a chiunque.
