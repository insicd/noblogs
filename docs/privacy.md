# Privacy

Questa pagina è per chi installa Noblogs: cosa raccoglie il codice, dove lo
mette, per quanto resta. L'informativa che vedono gli utenti è
`/privacy`, e deve restare allineata a quello che il codice fa davvero. Se
cambi `Hit.php`, `Upvote.php`, `Session.php` o `RateLimiter.php`, aggiorna
anche quella pagina.

## In una frase

Chi legge un blog ospite non riceve cookie, non viene profilato, non viene
inviato a servizi di terzi. Chi ha un account lascia un indirizzo email e
una password in hash. Le statistiche di lettura sono un conteggio, non un
diario.

## Lettori dei blog

Sulle pagine pubbliche **non si apre una sessione**. Nessun `Set-Cookie`.

Una lettura viene registrata solo se il visitatore muove il puntatore,
scorre o preme un tasto: lo script `hit.js` parte dopo quella interazione.
I programmi che scaricano la pagina non compaiono nei numeri.

Di ogni lettura Noblogs conserva:

| Campo | Cosa c'è davvero |
|---|---|
| `hash_id` | `sha256(ip + giorno UTC + analytics_salt)`. Cambia ogni notte, non si inverte senza il salt, non collega due giorni. |
| `hit_date` | Il giorno, non l'ora. |
| `post_id` | Quale testo (0 per la homepage). |
| `referrer` | Solo `schema://host`. Il percorso della pagina di partenza viene scartato. Le visite interne al blog non si contano. |
| `country` | Codice ISO a due lettere, e solo se un reverse proxy (Cloudflare, Fastly, CloudFront, App Engine) lo dichiara. Senza proxy il campo è vuoto. Nessun lookup GeoIP. |
| `device` / `browser` | Etichette generiche (`Android`, `Firefox`). Niente versione, niente impronta. |

Non si conservano: indirizzo IP, user agent grezzo, identificativo
persistente, cookie. La chiave unica `(blog, post, hash, giorno)` fa sì che
ricaricare la stessa pagina cento volte conti come una lettura.

Le righe più vecchie di `limits.analytics_retention` giorni (default 365)
le cancella la manutenzione. Con `0` restano per sempre: non è una buona
idea.

## Apprezzamenti

Un voto è `sha256(ip + anno + app_key)` per post. Un lettore può togliere il
voto. I voti con segnali sospetti (campo esca compilato, nessuna
interazione, user agent vuoto) vengono registrati ma non conteggiati: chi
li invia vede il pulsante cambiare e non ha motivo di insistere.

Anche qui niente cookie. Il token anti-CSRF è firmato, vive nella risposta
JSON e scade.

## Account

Di chi si registra Noblogs tiene:

- l'indirizzo email;
- l'hash della password (`password_hash` di PHP, oggi bcrypt);
- lingua, fuso, ruolo, tetto di blog, date di creazione e di ultimo accesso;
- token temporanei per verificare l'email e per reimpostare la password (il
  reset scade dopo due ore).

Non chiede nome, telefono, data di nascita, pagamento. Non c'è niente da
pagare.

Il cookie di sessione esiste **solo** sul dominio principale, dopo
l'accesso. I blog ospiti, anche se aperti nella stessa finestra, non lo
vedono: vivono su un altro host.

Chi elimina l'account perde i blog, gli articoli, i file e gli iscritti.
Non resta un archivio nascosto.

## Iscritti a un blog

Se l'autore attiva le iscrizioni, Noblogs chiede un indirizzo e manda un
messaggio di conferma (double opt-in). Senza conferma l'indirizzo non
riceve nulla e la manutenzione lo cancella entro una settimana.

Noblogs **non invia** la newsletter dei nuovi articoli. L'autore esporta gli
indirizzi e li usa con lo strumento che preferisce. È una scelta: mandare
posta di massa da un hosting condiviso è il modo più rapido per finire in
una blacklist, e mischiare quella posta con le email di sistema le rovina
tutte.

Il link per disiscriversi sta in calce a ogni messaggio di conferma, e su
una pagina del blog.

## File caricati

Vivono in `public/media/<sottodominio>/`. Dalle immagini, se c'è `gd`,
Noblogs toglie i metadati EXIF (GPS, fotocamera, data) e riduce il lato
lungo. I file non sono scanditi da un antivirus: il tipo si controlla per
estensione e, se c'è `fileinfo`, per contenuto.

Non caricare qui documenti che non vuoi pubblici. L'URL è prevedibile per
chi conosce il sottodominio.

## Log e limiti di frequenza

`storage/logs/error.log` può contenere percorsi e messaggi di eccezione.
Non dovrebbe contenere password (non vengono registrate); può contenere
query. Va tenuto fuori dal web: ci pensa `.htaccess` nella cartella
`storage/`.

Il limitatore di frequenza memorizza in `storage/` un hash dell'IP e un
contatore, per pochi minuti. Serve a fermare chi spara sulla login o sugli
apprezzamenti, non a costruire una storia.

## Cosa Noblogs non fa

- Non incorpora Google Analytics, né pixel, né font da CDN.
- Non manda i lettori su domini terzi (il foglio di stile è nella pagina).
- Non profila, non vende, non cede elenchi.
- Non tiene sessioni sui blog pubblici.
- Non legge la rubrica, la posizione, i contatti.

Se aggiungi uno script in `header_directive` o `footer_directive` di un
blog, sei tu a uscire da questo elenco. Quell'HTML lo decide l'autore, e
vale solo per quel blog.

## Responsabilità di chi installa

Noblogs è il software. Il titolare del trattamento è chi gira
l'installazione. Compila `site.contact_email`, rileggi `/privacy` e
`/termini` dopo aver cambiato nome e dominio, e non tenere `debug` attivo
in produzione: le pagine di errore mostrerebbero percorsi a chiunque.
