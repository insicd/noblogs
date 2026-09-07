# Noblogs

Piattaforma di blog hosting in PHP puro: una sola installazione ospita quanti
blog si vuole, ciascuno sul proprio sottodominio, con temi, statistiche,
iscrizioni via email e feed.

Nessun framework, nessun Composer, nessuna dipendenza esterna. Si carica via
FTP su un hosting condiviso da pochi euro l'anno e funziona.

```
esempio.tld              → pagina iniziale, registrazione, dashboard
miablog.esempio.tld      → il blog di qualcuno
esempio.tld/admin        → moderazione
```

## Cosa fa

**Per chi scrive**

- Articoli in Markdown, con estensioni proprie per note a margine, dettagli a
  fisarmonica, incorporamenti e didascalie.
- Pagine statiche, navigazione personalizzabile, percorso dell'archivio
  scelto liberamente.
- Bozze, pubblicazione programmata, anteprima con link firmato, duplicazione.
- Otto temi (Predefinito, Serif, Monospazio, Notte, Carta, Brutalista,
  Compatto, Terminale), tutti modificabili con CSS personalizzato: espongono
  le stesse proprietà in `:root`, quindi si può cambiare un colore senza
  riscrivere il foglio di stile.
- Caricamento di immagini con ridimensionamento e rimozione dei metadati EXIF.
- Statistiche di lettura senza cookie e senza servizi di terze parti.
- Iscrizioni via email con doppia conferma, esportabili.
- Feed Atom, sitemap, robots.txt, redirect definiti dall'autore.
- Esportazione completa in un archivio e importazione da Markdown.
- Dominio proprio al posto del sottodominio, se l'amministrazione lo consente.

**Per chi amministra**

- Coda di revisione dei nuovi blog, ordinata per punteggio di sospetto, con
  un estratto del contenuto per decidere in fretta.
- Azioni di moderazione registrate una per una in un registro consultabile.
- Gestione degli account: sospensione, verifica dell'email, ruoli, limiti.
- Impostazioni della piattaforma modificabili a caldo, senza toccare file.
- Installer web guidato e strumenti da riga di comando per il cron.

**Per chi legge**

- Nessun cookie, nessun tracciamento, nessuna richiesta a domini terzi.
- Pagine da poche decine di kilobyte, leggibili senza JavaScript.

## Requisiti

| | Minimo | Consigliato |
|---|---|---|
| PHP | 8.1 | 8.2 o 8.3 |
| MySQL | 5.7 | 8.0 |
| MariaDB | 10.3 | 10.6 |
| Spazio | 50 MB + i contenuti | |

Estensioni PHP obbligatorie: `pdo_mysql`, `mbstring`, `json`.

Estensioni facoltative ma consigliate:

| Estensione | Se manca |
|---|---|
| `gd` | le immagini non vengono ridimensionate né ripulite dai metadati EXIF |
| `dom` | l'HTML personalizzato viene filtrato in modo più grossolano |
| `zip` | l'esportazione produce un file di testo invece di un archivio |
| `intl` | la traslitterazione degli indirizzi è meno accurata |
| `fileinfo` | il tipo dei file caricati si deduce dall'estensione |

Non serve: Composer, Node, Redis, un gestore di code, un servizio esterno di
posta. La riscrittura degli URL (mod_rewrite su Apache, `try_files` su nginx)
sì.

## Installazione

### Hosting condiviso, via FTP

1. **Crea un database MySQL** dal pannello dell'hosting e prendi nota di host,
   nome, utente e password.

2. **Carica i file.** Se il pannello permette di scegliere la cartella
   pubblica di un dominio, punta la document root su `public/` e carica tutto
   il progetto fuori dalla portata del web. Altrimenti carica la cartella
   `noblogs/` dentro `public_html/`: il file `.htaccess` nella radice del
   progetto impedisce di raggiungere il codice e la configurazione.

3. **Rendi scrivibili tre cartelle**: `storage/`, `public/media/` e `config/`.
   Dal gestore file del pannello si imposta il permesso 755 (su alcuni
   hosting serve 775 o 777); da SSH:

   ```sh
   chmod -R 755 storage public/media config
   ```

4. **Apri il sito nel browser.** Senza `config/config.php` ogni richiesta
   finisce sull'installer, che verifica i requisiti, prova la connessione al
   database, crea le tabelle, popola i temi e crea il primo amministratore.

5. **Cancella la cartella `install/`** quando l'installer te lo chiede.

6. **Configura il cron** (vedi sotto). Se l'hosting non lo permette, la
   manutenzione si può eseguire a mano dal pannello di amministrazione.

La guida dettagliata, con le istruzioni specifiche per cPanel, Plesk e
DirectAdmin, è in [docs/installazione.md](docs/installazione.md).

### VPS

```sh
# 1. Codice
cd /var/www
git clone https://github.com/tuonome/noblogs.git
cd noblogs

# 2. Permessi: il codice appartiene a te, i dati al processo web
sudo chown -R www-data:www-data storage public/media config
sudo chmod -R 755 storage public/media config

# 3. Database
sudo mysql -e "CREATE DATABASE noblogs CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'noblogs'@'localhost' IDENTIFIED BY 'una-password-lunga';"
sudo mysql -e "GRANT ALL PRIVILEGES ON noblogs.* TO 'noblogs'@'localhost';"

# 4. Vhost
sudo cp noblogs.nginx.conf /etc/nginx/sites-available/noblogs
sudo $EDITOR /etc/nginx/sites-available/noblogs   # dominio e percorsi
sudo ln -s /etc/nginx/sites-available/noblogs /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

Poi apri il dominio nel browser e completa l'installer.

Su Apache basta puntare la document root su `public/`, attivare mod_rewrite e
permettere l'uso dei file `.htaccess` (`AllowOverride All`).

Al posto dell'installer web si può copiare `config/config.sample.php` in
`config/config.php`, riempirlo a mano, caricare lo schema e creare
l'amministratore da riga di comando:

```sh
mysql -u noblogs -p noblogs < db/schema.sql   # se il prefisso è vuoto
php bin/noblogs sincronizza-temi
php bin/noblogs crea-admin io@esempio.tld
```

Se il prefisso delle tabelle non è vuoto, `{{prefix}}` in `db/schema.sql` va
sostituito prima di caricarlo:

```sh
sed 's/{{prefix}}/nb_/g' db/schema.sql | mysql -u noblogs -p noblogs
```

## DNS per i sottodomini

Noblogs può far vivere i blog su un sottodominio
(`miablog.esempio.tld`) oppure su un percorso (`esempio.tld/miablog/`). La
scelta si fa durante l'installazione con `routing.mode` e si può cambiare
dopo.

### Modalità sottodominio

Servono due cose. La prima è un **record DNS wildcard**: nel pannello DNS del
dominio si aggiunge un record `A` (o `CNAME`) con nome `*`.

```
Tipo   Nome   Valore
A      @      203.0.113.10
A      *      203.0.113.10
```

Da quel momento qualunque nome che finisce in `.esempio.tld` arriva al
server, anche quelli che non esistono ancora: è proprio quello che serve,
perché i blog nascono senza che nessuno tocchi il DNS.

La seconda è un **vhost wildcard** sul server, cioè un blocco di
configurazione che accetta `*.esempio.tld` e passa a PHP il nome richiesto.
Con nginx è la riga `server_name esempio.tld *.esempio.tld;` di
`noblogs.nginx.conf`; con Apache è `ServerAlias *.esempio.tld`. Sugli hosting
condivisi si chiama di solito «sottodominio jolly» o «wildcard subdomain» e si
crea aggiungendo un sottodominio chiamato `*` che punta alla stessa cartella
del dominio principale.

Con HTTPS serve inoltre un **certificato wildcard** per `*.esempio.tld`. Con
Let's Encrypt richiede la validazione DNS-01 (non basta quella via HTTP) e
quindi un DNS con API, oppure una conferma manuale a ogni rinnovo:

```sh
sudo certbot certonly --manual --preferred-challenges dns \
     -d esempio.tld -d '*.esempio.tld'
```

### Modalità percorso

`routing.mode = 'path'` non richiede niente: né wildcard DNS, né vhost
particolari, né certificati speciali. I blog vivono su
`esempio.tld/miablog/`. È la scelta giusta su un hosting che non permette
sottodomini jolly.

C'è anche una via di mezzo: `routing.path_fallback = true` tiene i
sottodomini come indirizzo canonico ma lascia i blog raggiungibili anche via
percorso. Comodo per provare tutto prima di sistemare il DNS.

## Cron

Una riga, una volta al giorno:

```cron
0 4 * * * php /var/www/noblogs/bin/noblogs manutenzione >/dev/null 2>&1
```

Il comando pota le statistiche oltre il periodo di conservazione, cancella le
iscrizioni mai confermate e la cache scaduta, riallinea i temi e ricalcola i
contatori dei blog e i punteggi degli articoli. Non è indispensabile: senza
cron la piattaforma funziona, ma il database cresce senza sosta e la vetrina
si aggiorna solo quando un amministratore preme «Esegui la manutenzione» nel
pannello.

Sugli hosting condivisi il cron si configura dal pannello; se il percorso di
PHP non è nel `PATH`, va scritto per intero (`/usr/local/bin/php`).

Altri comandi:

```sh
php bin/noblogs aiuto                     # elenco e spiegazioni
php bin/noblogs statistiche               # riepilogo dell'installazione
php bin/noblogs crea-admin io@esempio.tld # crea o promuove un amministratore
php bin/noblogs sincronizza-temi           # dopo aver modificato db/themes.php
php bin/noblogs svuota-cache
```

## Aggiornamento

1. Fai una copia del database e della cartella `public/media/`.
2. Sovrascrivi i file, **tranne** `config/config.php`, `storage/` e
   `public/media/`.
3. Apri il sito: se lo schema è cambiato, le istruzioni per aggiornarlo sono
   nelle note di rilascio (Noblogs non modifica il database da solo).
4. Esegui `php bin/noblogs sincronizza-temi` e `php bin/noblogs svuota-cache`.

```sh
mysqldump -u noblogs -p noblogs > ~/noblogs-$(date +%F).sql
tar czf ~/noblogs-media-$(date +%F).tar.gz public/media
```

## Documentazione

- [docs/installazione.md](docs/installazione.md) — installazione passo per
  passo, pannelli di hosting, problemi frequenti.
- [docs/configurazione.md](docs/configurazione.md) — ogni opzione di
  `config.php`, spiegata.
- [docs/moderazione.md](docs/moderazione.md) — la coda di revisione, cosa
  fare con lo spam, come si usano gli strumenti.
- [docs/privacy.md](docs/privacy.md) — quali dati raccoglie il sistema, dove
  finiscono, quanto restano.
- [docs/sviluppo.md](docs/sviluppo.md) — architettura, come aggiungere un
  tema, come aggiungere una lingua.

## Struttura

```
app/          codice dell'applicazione (nessun file va richiesto direttamente)
  Core/       fondamenta: richiesta, risposta, router, viste, database, sessione
  Models/     accesso ai dati
  Controllers/ un file per area: Site, Platform, Dashboard, Admin, Auth
  Markdown/   il parser Markdown e la sua cache
  Views/      template PHP
  Support/    funzioni di servizio
bin/noblogs   strumenti da riga di comando
config/       configurazione (config.php non va versionato)
db/           schema del database e galleria dei temi
docs/         questa documentazione
install/      installer web, da cancellare dopo l'installazione
lang/         traduzioni
public/       l'unica cartella che va esposta al web
storage/      cache, registri, file temporanei
```

## Licenza

[GNU Affero General Public License v3.0](https://www.gnu.org/licenses/agpl-3.0.html).
In pratica: puoi usare, modificare e ridistribuire
Noblogs, anche in un servizio a pagamento, ma se lo fai devi rendere
disponibile il codice modificato a chi usa quel servizio.

## Ringraziamenti

Noblogs è ispirato a [BearBlog](https://bearblog.dev) nell'idea e in diverse
funzionalità: blog leggeri, senza tracciamento, senza fronzoli, con un
Markdown esteso e statistiche rispettose. L'implementazione è però originale e
non riusa codice di BearBlog, che è scritto in Python con Django: qui non
c'è niente di quel progetto, solo l'ammirazione per come ha dimostrato che un
blog non ha bisogno di due megabyte di JavaScript.
