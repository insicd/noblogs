# Configurazione

Ogni installazione di Noblogs ha un file `config/config.php`. L'installer lo
scrive al posto tuo; se lo copi da `config/config.sample.php` i commenti in
quel file coincidono con questa pagina.

Le chiavi si leggono con la notazione puntata (`site.domain`). Un valore
mancante ricade sul default di `config.sample.php`, quindi un file vecchio
resta valido dopo un aggiornamento: le chiavi nuove prendono il valore di
fabbrica finché non le aggiungi.

Alcune impostazioni si cambiano anche dal pannello, in `/admin/impostazioni`,
senza toccare il file. Quello che sta nel database ha la precedenza. Le
impostazioni che, sbagliate, ti chiuderebbero fuori — database, dominio,
routing, salt — restano solo in `config.php`.

## Sommario

- [Database](#database)
- [Piattaforma](#piattaforma)
- [Routing](#routing)
- [Sicurezza](#sicurezza)
- [Quote](#quote)
- [Posta](#posta)
- [Sviluppo](#sviluppo)
- [Cosa si cambia dal pannello](#cosa-si-cambia-dal-pannello)

## Database

| Chiave | Cosa fa |
|---|---|
| `db.host` | Host MySQL. Quasi sempre `localhost`; alcuni pannelli usano un nome proprio. |
| `db.port` | Porta, di norma 3306. |
| `db.name` | Nome del database. |
| `db.user` / `db.password` | Credenziali. Il pannello dell'hosting spesso antepone il tuo utente al nome. |
| `db.prefix` | Prefisso delle tabelle. Vuoto se il database è dedicato a Noblogs; altrimenti `nb_` o simile, con il trattino basso finale. |
| `db.socket` | Socket Unix, se l'hosting non usa la porta TCP di default. `null` nella maggioranza dei casi. |

Dopo aver cambiato host, utente o password, `php bin/noblogs statistiche` è il
modo più rapido per verificare che la connessione tenga.

## Piattaforma

| Chiave | Cosa fa |
|---|---|
| `site.domain` | Dominio principale: landing, registrazione, dashboard. I blog vivono sui suoi sottodomini. |
| `site.extra_hosts` | Altri host trattati come dominio principale (`www`, `localhost`, uno staging). |
| `site.name` | Nome mostrato in testata, nei feed e nel piè di pagina dei blog. |
| `site.tagline` | Motto, sotto il nome nella pagina iniziale. |
| `site.https` | Se `true`, tutti gli URL generati usano `https://`. |
| `site.email_from` | Mittente delle email di sistema (verifica, password, conferma iscrizione). |
| `site.email_name` | Nome del mittente. |
| `site.contact_email` | Indirizzo mostrato in privacy, termini e pagine di contatto. |
| `site.locale` | Lingua di default della piattaforma (`it` o `en`). |
| `site.timezone` | Fuso usato per mostrare le date nel pannello. I timestamp in database restano in UTC. |
| `site.cache_seconds` | Secondi di cache condivisa delle pagine pubbliche (default 300). Assente dal file di esempio: si può aggiungere. |

`site.domain` va scritto **senza** schema e **senza** `www`: `esempio.tld`.

## Routing

| Chiave | Cosa fa |
|---|---|
| `routing.mode` | `subdomain` → `miablog.esempio.tld`. `path` → `esempio.tld/miablog/`. |
| `routing.path_fallback` | Con `true` i blog restano raggiungibili anche via percorso, anche se il canone è il sottodominio. Utile prima che il DNS jolly sia a posto. |
| `routing.custom_domains` | Consente a un blog di rispondere su un dominio proprio. |

La guida DNS è nel [README](../README.md#dns-per-i-sottodomini). In sintesi:
modalità sottodominio vuole un record `A` (o `CNAME`) su `*` e un vhost
wildcard; in HTTPS serve un certificato `*.esempio.tld`.

Se un blog ha un dominio proprio, le visite al sottodominio (e al percorso di
fallback) vengono reindirizzate in 301 verso quel dominio. Il fallback da
solo, senza dominio proprio, non reindirizza: è fatto per convivere.

## Sicurezza

| Chiave | Cosa fa |
|---|---|
| `security.analytics_salt` | Entra nell'impronta giornaliera delle statistiche. Cambiarlo spezza la continuità dei visitatori unici. |
| `security.app_key` | Firma sessioni, apprezzamenti e link di anteprima. Cambiarlo disconnette tutti. |
| `security.require_email_verification` | I nuovi account devono confermare l'indirizzo prima di creare un blog. |
| `security.require_blog_review` | I nuovi blog restano `noindex` e fuori dalla vetrina finché un moderatore non li approva. |
| `security.reserved_subdomains` | Nomi che nessuno può registrare (`www`, `admin`, `mail`, …). |
| `security.trusted_proxies` | IP dei reverse proxy da cui accettare `X-Forwarded-For` e `CF-Connecting-IP`. Vuoto se PHP vede già l'IP del visitatore. |
| `security.extra_iframe_hosts` | Host aggiuntivi da cui è lecito incorporare un iframe nei testi (oltre a YouTube e Vimeo). |

I due salt li genera l'installer. Se installi a mano:

```sh
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
```

`trusted_proxies` va riempito solo se c'è un proxy davanti a PHP
(Cloudflare, nginx, un load balancer). Altrimenti chiunque potrebbe spacciarsi
per un altro indirizzo, e l'indirizzo entra — in forma di hash — nelle
statistiche e negli apprezzamenti.

Il paese nelle statistiche non si calcola da un database GeoIP: si legge
l'intestazione che Cloudflare (`CF-IPCountry`), Fastly, CloudFront o App
Engine aggiungono già. Senza proxy, la colonna resta vuota.

## Quote

Servono a contenere gli abusi, non a spingere verso un piano a pagamento.

| Chiave | Default | Cosa limita |
|---|---|---|
| `limits.blogs_per_user` | 3 | Blog per account. Si può alzare per un utente solo dal pannello. |
| `limits.posts_per_blog` | 5000 | Articoli e pagine sommati. |
| `limits.post_max_chars` | 1 000 000 | Lunghezza di un singolo testo. |
| `limits.upload_max_bytes` | 10 MB | Un file caricato. |
| `limits.storage_per_blog` | 500 MB | Spazio totale dei file di un blog. |
| `limits.files_per_blog` | 2000 | Numero di file. |
| `limits.image_max_width` | 1600 | Lato lungo a cui vengono ridotte le immagini (serve l'estensione `gd`). |
| `limits.analytics_retention` | 365 | Giorni di conservazione delle letture. `0` le tiene per sempre. |

Il cron (`php bin/noblogs manutenzione`) pota le statistiche oltre la
conservazione. Senza cron il database cresce e basta.

## Posta

| Chiave | Cosa fa |
|---|---|
| `mail.driver` | `mail` usa `mail()` di PHP. `smtp` si collega a un server. `log` scrive in `storage/logs/mail.log` senza inviare. |
| `mail.smtp.host` / `port` / `user` / `password` | Credenziali SMTP. |
| `mail.smtp.encryption` | `tls`, `ssl` oppure `null`. |

Su hosting condiviso `mail` è spesso l'unica opzione, e i messaggi finiscono
in spam se il mittente non coincide col dominio. `smtp` con una casella dello
stesso dominio passa molto meglio i filtri. `log` è quello da usare in locale.

## Sviluppo

| Chiave | Cosa fa |
|---|---|
| `debug` | Con `true` le pagine di errore mostrano traccia e frammenti di codice. Va tenuto `false` in produzione. |

Gli errori finiscono comunque in `storage/logs/error.log`.

## Cosa si cambia dal pannello

In `/admin/impostazioni`, senza toccare il file:

- nome e motto del sito;
- email di contatto;
- registrazioni aperte o chiuse;
- verifica dell'email obbligatoria;
- revisione dei nuovi blog;
- quote predefinite (blog per utente, articoli, spazio, dimensione dei file);
- un avviso di piattaforma, mostrato nelle pagine pubbliche.

Svuotare un campo nel pannello fa tornare il valore di `config.php`.
