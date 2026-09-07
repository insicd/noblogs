<?php
/**
 * Condizioni d'uso: corte, in italiano, senza legalese inutile.
 *
 * @var \Noblogs\Core\View $this
 * @var string $contact
 * @var bool   $review
 */

use Noblogs\Core\Config;
use Noblogs\Core\Url;

$siteName = (string) ($siteName ?? Config::get('site.name', 'Noblogs'));

$this->layout('layouts/platform');
$this->start('content');
?>
<div class="pf-shell pf-prose">
  <h1>Condizioni d'uso</h1>

  <p class="pf-lead">
    <?= e($siteName) ?> è un servizio gratuito, offerto così com'è. Queste sono le poche regole che
    lo tengono in piedi. Usando il servizio le accetti.
  </p>

  <h2>Cosa puoi fare</h2>
  <p>
    Aprire un blog e scrivere quello che vuoi, in qualsiasi lingua, con lo stile che preferisci.
    I contenuti che pubblichi restano tuoi: non ne rivendichiamo la proprietà e non li usiamo per
    altro. Ci autorizzi soltanto a conservarli e a mostrarli ai lettori, che è esattamente ciò che
    ti serve da un servizio di hosting.
  </p>

  <h2>Cosa non è ammesso</h2>
  <ul>
    <li>contenuti illegali secondo la legge applicabile a chi gestisce il servizio;</li>
    <li>materiale che ritrae abusi su minori, in qualunque forma;</li>
    <li>istigazione alla violenza, minacce, molestie, discriminazione o campagne contro persone
        o gruppi;</li>
    <li>diffusione di dati personali di altre persone senza il loro consenso;</li>
    <li>phishing, malware, pagine costruite per imitare un altro servizio e sottrarre credenziali;</li>
    <li>spam, reti di siti creati per manipolare i motori di ricerca, vendita di link;</li>
    <li>contenuti su cui non hai diritti: testi, immagini o musica di altri ripubblicati senza
        permesso;</li>
    <li>usare il servizio come deposito di file, come proxy, come CDN o per far girare qualcosa
        che non sia un blog;</li>
    <li>tentare di aggirare i limiti tecnici, sovraccaricare il server, interferire con gli altri
        blog ospitati.</li>
  </ul>
  <p>
    Se hai un dubbio su un contenuto al limite, chiedi prima<?php if ($contact !== ''): ?>:
    <a href="mailto:<?= e($contact) ?>"><?= e($contact) ?></a><?php endif; ?>. Una risposta costa
    meno di un blog cancellato.
  </p>

  <h2>Moderazione</h2>
  <?php if ($review): ?>
    <p>
      La registrazione è aperta e non richiede approvazione: il tuo blog è online appena lo crei.
      Fino a quando un moderatore non lo esamina, però, il blog resta <strong>fuori dagli indici
      dei motori di ricerca</strong> e fuori dalla <a href="<?= e(Url::to('/esplora')) ?>">vetrina</a>.
      Non è un giudizio su di te: è ciò che rende inutile aprire blog per fare spam, e quindi ciò
      che permette di tenere la registrazione aperta a tutti gli altri.
    </p>
  <?php endif; ?>
  <p>
    Quando una segnalazione o un controllo mostra una violazione, un moderatore può nascondere un
    articolo, togliere un blog dalla vetrina, renderlo invisibile o cancellarlo, e nei casi gravi
    disattivare l'account. Cerchiamo di avvisare e di spiegare, e nei casi meno gravi di chiedere
    una correzione prima di intervenire. Per i contenuti che mettono in pericolo qualcuno
    interveniamo subito e spieghiamo dopo.
  </p>
  <p>
    Se pensi che una decisione sia sbagliata, scrivici<?php if ($contact !== ''): ?> a
    <a href="mailto:<?= e($contact) ?>"><?= e($contact) ?></a><?php endif; ?>: ci sono errori
    anche da questa parte, e si correggono.
  </p>

  <h2>Il tuo account</h2>
  <ul>
    <li>Sei responsabile di quello che pubblichi e di tenere al sicuro la tua password.</li>
    <li>Un account per persona, e un numero limitato di blog per account: il limite serve a
        distribuire le risorse, non a venderti un piano superiore.</li>
    <li>Puoi <strong>cancellare l'account quando vuoi</strong>, dalla dashboard, senza scrivere a
        nessuno e senza spiegare perché. La cancellazione porta via l'account, i suoi blog, gli
        articoli, i file caricati, le statistiche e le liste di iscritti. È immediata e definitiva:
        se ci tieni ai tuoi testi, <a href="<?= e(Url::to('/aiuto')) ?>">esportali prima</a>.</li>
    <li>Un account inattivo e senza contenuti può essere rimosso dopo un lungo periodo, per non
        tenere occupati indirizzi di blog che nessuno usa. Se hai pubblicato qualcosa, quello resta.</li>
  </ul>

  <h2>Nessuna garanzia</h2>
  <p>
    Il servizio è fornito «così com'è», senza garanzie di funzionamento, di disponibilità o di
    conservazione dei dati. Può essere interrotto, può perdere dati, può chiudere. Chi lo gestisce
    non risponde di danni derivanti dall'uso o dall'indisponibilità del servizio, né dei contenuti
    pubblicati dagli utenti, nei limiti consentiti dalla legge.
  </p>
  <p>
    Detto senza formule: <strong>tieni una copia di quello che scrivi.</strong> La dashboard
    permette di esportare tutto in file Markdown in qualsiasi momento; fallo di tanto in tanto.
    Vale per questo servizio come per qualunque altro.
  </p>

  <h2>Modifiche</h2>
  <p>
    Queste condizioni possono cambiare: la versione valida è sempre quella pubblicata su questa
    pagina. Se una modifica non ti sta bene, puoi esportare i tuoi contenuti e chiudere l'account.
  </p>

  <p class="pf-form-links">
    <a href="<?= e(Url::to('/privacy')) ?>">Informativa sulla privacy</a>
    <span aria-hidden="true">·</span>
    <a href="<?= e(Url::to('/aiuto')) ?>">Aiuto</a>
  </p>
</div>
<?php
$this->end();
echo $this->slot('content'); // vedi la nota sullo slot in layouts/platform.php
