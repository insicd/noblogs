<?php
/**
 * Informativa sulla privacy.
 *
 * Ogni affermazione di questa pagina corrisponde a qualcosa che il codice fa
 * davvero: app/Models/Hit.php per le statistiche, app/Models/Upvote.php per gli
 * apprezzamenti, app/Core/Session.php per il cookie di sessione,
 * app/Core/RateLimiter.php per i limiti di frequenza. Chi modifica quei file
 * deve aggiornare anche questa pagina.
 *
 * @var \Noblogs\Core\View $this
 * @var string $contact
 * @var string $domain
 * @var int    $retention
 * @var bool   $verifyEmail
 */

use Noblogs\Core\Config;
use Noblogs\Core\Url;

$siteName = (string) ($siteName ?? Config::get('site.name', 'Noblogs'));

$this->layout('layouts/platform');
$this->start('content');
?>
<div class="pf-shell pf-prose">
  <h1>Informativa sulla privacy</h1>

  <p class="pf-lead">
    Questa pagina descrive quali dati raccoglie <?= e($siteName) ?>, come li usa e per quanto tempo
    li conserva. È scritta per essere letta: se qualcosa non è chiaro, scrivici e la correggiamo.
  </p>

  <h2>In breve</h2>
  <ul>
    <li>Non conserviamo nessun indirizzo IP.</li>
    <li>Non usiamo cookie di tracciamento né di profilazione. Chi legge un blog non riceve
        nessun cookie.</li>
    <li>Non usiamo servizi di terze parti: nessuna analitica esterna, nessun font remoto,
        nessuna rete pubblicitaria, nessun pulsante social.</li>
    <li>Le statistiche di lettura si basano su un'impronta giornaliera non reversibile e non
        permettono di seguire una persona da un giorno all'altro.</li>
    <li>Non vendiamo, non cediamo e non condividiamo dati con nessuno.</li>
  </ul>

  <h2>Chi tratta i dati</h2>
  <p>
    Il titolare del trattamento è chi gestisce questa installazione di <?= e($siteName) ?>, sul
    dominio <code><?= e($domain) ?></code>.
    <?php if ($contact !== ''): ?>
      Per qualsiasi richiesta l'indirizzo è <a href="mailto:<?= e($contact) ?>"><?= e($contact) ?></a>.
    <?php else: ?>
      Chi gestisce questa installazione non ha pubblicato un indirizzo di contatto: chiedilo a chi
      ti ha dato l'indirizzo del servizio.
    <?php endif; ?>
  </p>

  <h2>Se hai un account</h2>
  <p>Del tuo account conserviamo:</p>
  <ul>
    <li>l'<strong>indirizzo email</strong>, che serve
        <?php if ($verifyEmail): ?>a verificare la registrazione e<?php endif; ?>
        a recuperare la password. Non è visibile sul tuo blog né ad altri utenti;</li>
    <li>la <strong>password</strong>, mai in chiaro: ne conserviamo solo un hash calcolato con
        l'algoritmo predefinito di PHP (attualmente bcrypt), da cui la password non si può ricavare;</li>
    <li>lingua e fuso orario che hai scelto, il numero massimo di blog consentito, la data di
        creazione dell'account e quella dell'ultimo accesso;</li>
    <li>i <strong>token temporanei</strong> per la verifica dell'indirizzo e per la reimpostazione
        della password. Quello di reimpostazione scade dopo due ore e viene cancellato appena
        la password è stata cambiata.</li>
  </ul>
  <p>
    Non chiediamo il nome, non chiediamo il numero di telefono, non chiediamo dati di pagamento:
    su <?= e($siteName) ?> non c'è nulla da pagare.
  </p>
  <p>
    Quello che scrivi nei tuoi blog — articoli, pagine, file che carichi — è contenuto tuo, e
    resta pubblicamente accessibile finché lo lasci pubblicato.
  </p>

  <h2>Statistiche di lettura</h2>
  <p>
    Ogni blog può tenere le statistiche accese o spente. Quando sono accese, la lettura di una
    pagina produce una riga con questi campi, e nient'altro:
  </p>
  <dl>
    <dt>Un'impronta giornaliera del lettore</dt>
    <dd>
      È l'hash SHA-256 di tre cose messe insieme: l'indirizzo IP, la data di oggi e un valore
      segreto dell'installazione (il <em>salt</em>). Non è reversibile senza il salt, e siccome
      contiene la data <strong>cambia ogni notte</strong>: la stessa persona che torna domani è
      un'impronta diversa, e non esiste modo di collegare le due. Serve a un solo scopo: contare
      una lettura per pagina al giorno, così ricaricare venti volte lo stesso articolo non conta
      venti letture.
    </dd>

    <dt>Il blog e l'articolo letti, con la data e l'istante</dt>
    <dd>
      La data serve ai conteggi giornalieri; l'istante della registrazione serve a mostrare a chi
      tiene il blog quante persone stanno leggendo in questo momento. Nessuno dei due è collegabile
      a una persona, perché l'impronta che accompagna la riga non lo è.
    </dd>

    <dt>La provenienza, ridotta a schema e dominio</dt>
    <dd>
      Se arrivi da un altro sito conserviamo soltanto qualcosa come
      <code>https://esempio.org</code>: mai il percorso completo, che direbbe cosa stavi leggendo
      altrove. Gli arrivi da un'altra pagina dello stesso blog non vengono registrati affatto.
    </dd>

    <dt>Sistema operativo e browser, come etichette generiche</dt>
    <dd>
      Solo una parola da un elenco chiuso — per il sistema operativo Android, iOS, Windows,
      macOS, ChromeOS o Linux; per il browser Firefox, Edge, Opera, Chrome o Safari — e nulla se
      non rientra in nessuna di queste. Nessun numero di versione, nessuna risoluzione dello
      schermo, nessun elenco di font: niente che serva a costruire un'impronta del dispositivo.
      La stringa completa inviata dal browser (lo <em>user agent</em>) non viene salvata.
    </dd>
  </dl>

  <p><strong>Cosa non finisce in quella riga:</strong></p>
  <ul>
    <li>l'indirizzo IP, in nessuna forma conservabile: entra nel calcolo dell'impronta e viene
        buttato via;</li>
    <li>la stringa dello user agent;</li>
    <li>nessun identificativo di sessione, nessun cookie, nessun numero che sopravviva alla
        giornata;</li>
    <li>nessuna geolocalizzazione: non risaliamo al paese, alla città né all'operatore. La tabella
        prevede una colonna per il paese, ma il codice non la riempie mai.</li>
  </ul>

  <p>
    La richiesta che registra la lettura viene inviata da un piccolo script incluso nelle pagine
    dei blog che hanno le statistiche accese. I client che si dichiarano automatici (motori di
    ricerca, sonde di monitoraggio, strumenti da riga di comando) vengono scartati. Chi blocca gli
    script non compare nei conteggi e non perde nulla della pagina.
  </p>

  <h2>Apprezzamenti agli articoli</h2>
  <p>
    Un lettore può segnalare che un articolo gli è piaciuto senza registrarsi. Anche qui l'identità
    è un hash SHA-256 non reversibile, calcolato però su indirizzo IP, <strong>anno</strong> e salt:
    serve a consentire un voto per articolo, non a riconoscere chi vota. Insieme al voto può essere
    conservata una nota tecnica quando il voto sembra automatizzato — un'indicazione sul
    comportamento della richiesta, non un dato su una persona. Nemmeno qui si conserva l'IP.
  </p>

  <h2>Limiti di frequenza</h2>
  <p>
    Per fermare i tentativi di indovinare una password e gli invii di moduli in massa contiamo i
    tentativi in una finestra di tempo. La riga che li conta è identificata da un <em>HMAC</em>
    dell'indirizzo IP calcolato con la chiave dell'installazione, non dall'IP: nella tabella non
    c'è nulla di leggibile. Le righe scadute vengono cancellate.
    Lo stesso vale per i tentativi contati per indirizzo email: nella tabella finisce un HMAC,
    non l'indirizzo.
  </p>

  <h2>Cookie</h2>
  <p>
    <?= e($siteName) ?> usa <strong>un solo cookie</strong>, e solo quando serve davvero:
  </p>
  <table>
    <thead>
      <tr><th scope="col">Nome</th><th scope="col">Quando</th><th scope="col">A cosa serve</th><th scope="col">Durata</th></tr>
    </thead>
    <tbody>
      <tr>
        <td><code>noblogs_session</code></td>
        <td>Solo su <code><?= e($domain) ?></code>, e solo se accedi a un account</td>
        <td>Tenere aperta la sessione e proteggere i moduli dalle richieste falsificate</td>
        <td>Fino alla chiusura del browser</td>
      </tr>
    </tbody>
  </table>
  <p>
    Il cookie è <code>HttpOnly</code> (non leggibile dagli script) e <code>SameSite=Lax</code> (non
    viaggia con le richieste che arrivano da altri siti); se l'installazione è servita in HTTPS è
    anche <code>Secure</code>. È un cookie tecnico: non serve un consenso e non c'è niente da
    accettare.
  </p>
  <p>
    <strong>Sui blog non viene aperta nessuna sessione e non viene mandato nessun cookie.</strong>
    Chi legge un blog ospitato qui non riceve niente da conservare — nemmeno il modulo di
    iscrizione ne ha bisogno: usa un token firmato che viaggia dentro il modulo stesso.
  </p>

  <h2>Email che inviamo</h2>
  <p>Il servizio ti scrive soltanto per queste ragioni:</p>
  <ul>
    <?php if ($verifyEmail): ?><li>confermare l'indirizzo email alla registrazione;</li><?php endif; ?>
    <li>reimpostare la password, quando lo chiedi tu;</li>
    <li>avvisarti che qualcuno ha provato a registrarsi con il tuo indirizzo (in quel caso non
        creiamo nessun account e non diciamo a chi ha compilato il modulo che l'indirizzo esiste);</li>
    <li>confermare l'iscrizione agli aggiornamenti di un blog, se ti iscrivi.</li>
  </ul>
  <p>
    Nessuna newsletter, nessun messaggio pubblicitario, nessun sollecito. L'invio passa dal server
    di posta configurato da chi gestisce l'installazione.
  </p>

  <h2>Iscrizioni agli aggiornamenti di un blog</h2>
  <p>
    Se un blog ha attivato le iscrizioni e tu lasci il tuo indirizzo, conserviamo l'indirizzo, la
    data e un token di conferma. L'iscrizione richiede sempre una conferma dal messaggio che ti
    arriva: senza, nessuno può iscrivere l'indirizzo di qualcun altro. Gli indirizzi mai confermati
    vengono cancellati dopo sette giorni. Chi tiene il blog vede gli indirizzi confermati, perché è
    a lui che servono per scrivere ai suoi lettori. Ogni messaggio contiene il link per cancellarsi,
    che funziona subito e senza spiegazioni.
  </p>

  <h2>Contenuti che arrivano da altri siti</h2>
  <p>
    Chi scrive un blog può inserire un'immagine ospitata altrove o incorporare un video da una
    lista chiusa di servizi noti. In quel caso il tuo browser contatta quel servizio per caricare
    la risorsa, e quel servizio vedrà la richiesta: è una scelta di chi ha scritto l'articolo, non
    nostra. Sugli <code>iframe</code> imponiamo <code>referrerpolicy="no-referrer"</code>, così il
    servizio esterno non riceve l'indirizzo della pagina che lo contiene. Le pagine della
    piattaforma — questa compresa — non caricano nulla da domini terzi.
  </p>

  <h2>Per quanto tempo</h2>
  <ul>
    <li>
      <strong>Statistiche di lettura:</strong>
      <?php if ($retention > 0): ?>
        <?= e((string) $retention) ?> giorni, poi le righe più vecchie vengono cancellate.
      <?php else: ?>
        questa installazione è configurata per non cancellarle automaticamente.
      <?php endif; ?>
      Il valore è un'impostazione dell'installazione (<code>limits.analytics_retention</code>).
    </li>
    <li><strong>Apprezzamenti:</strong> finché l'articolo esiste; l'impronta di chi ha votato
        diventa comunque inservibile dopo un anno, perché il calcolo cambia.</li>
    <li><strong>Limiti di frequenza:</strong> minuti o ore, quanto dura la finestra del limite.</li>
    <li><strong>Account, blog e articoli:</strong> finché li tieni. Se cancelli l'account, blog,
        articoli, file caricati, statistiche e iscritti vengono cancellati insieme a lui.</li>
    <li><strong>Registro degli errori:</strong> il server conserva un file con gli errori del
        programma (messaggio e traccia del codice) per poterli correggere. Non contiene indirizzi IP.</li>
  </ul>
  <p>
    Il server web che sta davanti all'applicazione può tenere un proprio registro degli accessi,
    con gli indirizzi IP, secondo la configurazione di chi lo gestisce: è un livello che non
    dipende da questo programma. Chiedi a chi gestisce l'installazione com'è configurato.
  </p>

  <h2>I tuoi diritti</h2>
  <p>
    Il trattamento avviene ai sensi del Regolamento (UE) 2016/679 (GDPR). La base giuridica è
    l'esecuzione del servizio che ci hai chiesto (art. 6, par. 1, lett. b) e, per le misure che
    difendono il servizio dagli abusi, il legittimo interesse (lett. f).
  </p>
  <p>Nei limiti previsti dagli articoli 15-22 del GDPR hai diritto a:</p>
  <ul>
    <li>sapere quali tuoi dati trattiamo e ottenerne una copia (accesso e portabilità);</li>
    <li>correggerli, se sono sbagliati;</li>
    <li>cancellarli: la dashboard ti permette di cancellare l'account e i suoi contenuti da solo,
        in qualsiasi momento e senza chiedere niente a nessuno;</li>
    <li>chiedere la limitazione del trattamento e opporti a quello basato sul legittimo interesse;</li>
    <li>proporre reclamo all'autorità di controllo — in Italia il Garante per la protezione dei
        dati personali.</li>
  </ul>
  <p>
    Una precisazione onesta sull'accesso: le statistiche di lettura e gli apprezzamenti non sono
    collegabili a te, perché l'impronta non è reversibile e cambia ogni giorno. Non possiamo
    estrarre «i dati di lettura di una certa persona» nemmeno volendo — ed è esattamente il motivo
    per cui il sistema è fatto così.
  </p>
  <?php if ($contact !== ''): ?>
    <p>
      Per esercitare questi diritti scrivi a <a href="mailto:<?= e($contact) ?>"><?= e($contact) ?></a>.
    </p>
  <?php endif; ?>

  <h2>Modifiche</h2>
  <p>
    Se cambia il modo in cui il programma tratta i dati, cambia anche questa pagina: è scritta
    guardando il codice, non a parte. Il codice è pubblico e verificabile, quindi puoi controllare
    tu stesso che le due cose coincidano.
  </p>

  <p class="pf-form-links">
    <a href="<?= e(Url::to('/termini')) ?>">Condizioni d'uso</a>
    <span aria-hidden="true">·</span>
    <a href="<?= e((string) Config::get('site.source_url', 'https://noblogs.dev')) ?>" rel="noopener">Codice sorgente</a>
  </p>
</div>
<?php
$this->end();
echo $this->slot('content'); // vedi la nota sullo slot in layouts/platform.php
