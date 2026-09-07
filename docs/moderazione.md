# Moderazione

Noblogs è fatto per ospitare i blog di altri. Prima o poi qualcuno userà lo
spazio per spam, phishing o contenuti che non vuoi sulla tua installazione.
Questa pagina spiega gli strumenti, e in quale ordine usarli.

L'area di amministrazione vive su `/admin`. Non è linkata dalle pagine
pubbliche: chi non ha i permessi riceve un 404, non un «vietato», così non
scopre nemmeno che esiste. Servono il ruolo `moderator` o `admin`.

## Sommario

- [Cosa vede chi arriva](#cosa-vede-chi-arriva)
- [La coda](#la-coda)
- [Le azioni](#le-azioni)
- [Account](#account)
- [Registro](#registro)
- [HTML libero](#html-libero)
- [Manutenzione](#manutenzione)

## Cosa vede chi arriva

Con `security.require_blog_review` attivo (è il default) un blog appena
creato:

- è raggiungibile sul suo sottodominio;
- ha `noindex` e non compare nella vetrina (`/esplora`);
- mostra in piè di pagina la nota «Blog in attesa di revisione».

Chi lo ha creato può scrivere, caricare file e scegliere un tema. Chi lo
scopre dall'esterno può leggerlo se ha il link, ma i motori di ricerca e la
vetrina lo ignorano finché non lo approvi.

Se disattivi la revisione, i blog nuovi sono pubblici subito. Ha senso solo
su un'installazione chiusa, con account che conosci.

## La coda

La prima cosa che vedi in `/admin` è la coda: i blog con `reviewed = 0` o
rimessi in revisione (`to_review`). Sono ordinati per **punteggio di
sospetto**, dal più alto in giù.

Il punteggio non nasconde niente da solo: è un ordinamento. Sale quando la
homepage è quasi vuota, quando è piena di collegamenti, quando il blog
pubblica a raffica, o quando nel testo comparono parole tipiche dello spam
(`casino`, `viagra`, `crypto`, `forex`, …). Un blog onesto con la homepage
ancora da scrivere può avere un punteggio basso ma non zero: aprilo lo
stesso.

Per ogni voce la coda mostra:

- titolo e sottodominio;
- chi lo ha creato, e da quanto;
- quanti articoli ci sono;
- un estratto della homepage e i titoli recenti.

Quasi sempre basta quello per decidere, senza aprire il blog.

## Le azioni

Da `/admin/blog` (e dai pulsanti in coda) su un blog si può:

| Azione | Effetto |
|---|---|
| **Approva** | Esce dalla coda, sparisce il `noindex`, può comparire in vetrina se l'autore lo ha lasciato scopribile. |
| **Nascondi** | Resta raggiungibile da chi ha il link, sparisce da vetrina e indici. Utile per un blog che non è spam ma non vuoi promuovere. |
| **Mostra** | Toglie il nascondimento. |
| **Segnala** | Lo marca e lo rimette in coda. Serve quando qualcosa di già approvato è degenerato. |
| **Togli la segnalazione** | Torna nello stato precedente. |
| **Consenti HTML libero** | Disattiva la bonifica dell'HTML nei testi di quel blog. Da usare solo se ti fidi: un sottodominio con HTML libero può ospitare una pagina di raccolta credenziali. |
| **Revoca HTML libero** | Rimette la bonifica. |
| **Elimina** | Cancella blog, articoli, file, iscritti. Chiede di ridigitare il sottodominio. Irreversibile. |

Ogni azione finisce nel [registro](#registro). La nota del moderatore resta
attaccata al blog quando serve all'autore (approvazione, nascondimento,
segnalazione); le altre restano solo nel registro.

Un blog **flaggato** non entra in vetrina, anche se è stato approvato in
passato.

## Account

In `/admin/utenti` si gestiscono gli account:

- sospendere (`is_active = 0`): non entra più, i suoi blog restano visibili
  finché non li nascondi o li elimini;
- marcare l'email come verificata, se la conferma non è arrivata;
- cambiare il ruolo (`user`, `moderator`, `admin`);
- alzare o abbassare il numero massimo di blog di quella persona;
- eliminare l'account. Eliminare un utente cancella anche i suoi blog
  (vincolo di chiave esterna).

L'eliminazione chiede conferma. Non c'è un cestino.

## Registro

`/admin/registro` elenca ogni azione di moderazione: chi, su cosa, quando,
con quale nota. Non si cancella dalle pagine pubbliche. Serve a capire cosa
è successo tre mesi dopo, e a due persone dello staff per non pestarsi i
piedi.

La manutenzione periodica ci scrive una riga, così si vede se il cron gira.

## HTML libero

Di default l'HTML nei testi passa da un filtro: restano i tag innocui,
spariscono script, eventi e iframe verso host sconosciuti. YouTube e Vimeo
sono nella lista. Altri host si aggiungono in
`security.extra_iframe_hosts`.

«Consenti HTML libero» salta quel filtro per un singolo blog. Non è una
scorciatoia per gli autori: è una deroga che dai tu, blog per blog, e che
puoi revocare. Se un autore te lo chiede, chiediti perché il Markdown e gli
iframe già ammessi non gli bastano.

## Manutenzione

Dal quadro generale, o da riga di comando:

```sh
php bin/noblogs manutenzione
```

Pota le statistiche oltre il periodo di conservazione, cancella le
iscrizioni mai confermate, svuota la cache scaduta, riallinea i temi,
ricalcola i contatori dei blog (ultimo articolo, raffiche delle ultime 12
ore, punteggio di sospetto, spazio occupato) e i punteggi della vetrina.

Senza questa riga il sito funziona. Il database cresce, la coda non si
riordina da sola, la vetrina invecchia.

Se l'hosting non ha il cron, il pulsante nel pannello fa le stesse cose.
Una volta al giorno basta.
