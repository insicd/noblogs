<?php
/**
 * Informazioni sulla piattaforma. Testo in italiano direttamente nella vista:
 * è prosa, non etichette, e qui si rilegge meglio.
 *
 * @var \Noblogs\Core\View $this
 * @var string $contact
 * @var string $version
 */

use Noblogs\Core\Config;
use Noblogs\Core\Url;

$siteName = (string) ($siteName ?? Config::get('site.name', 'Noblogs'));

$this->layout('layouts/platform');
$this->start('content');
?>
<div class="pf-shell pf-prose">
  <h1>Informazioni su <?= e($siteName) ?></h1>

  <p class="pf-lead">
    <?= e($siteName) ?> è un servizio di blog gratuito e attento alla privacy. Scrivi in Markdown,
    la pagina che ne esce è HTML e un foglio di stile, e chi la legge non viene seguito da nessuna parte.
  </p>

  <h2>Perché esiste</h2>
  <p>
    Pubblicare un testo su internet è diventato complicato: piattaforme che chiedono un abbonamento
    per usare un dominio proprio, pagine che pesano megabyte, script di terze parti che raccolgono
    dati su chi legge. Non serve niente di tutto questo per scrivere e per essere letti.
  </p>
  <p>
    <?= e($siteName) ?> prende l'idea di <span lang="en">BearBlog</span> — un blog è un titolo, del
    testo e un indirizzo — e la porta fino in fondo: nessun piano a pagamento, nessuna funzione
    riservata a chi paga, nessuna pubblicità. Il servizio costa poco da mantenere proprio perché
    fa poche cose.
  </p>

  <h2>Come è fatto</h2>
  <ul>
    <li><strong>Niente tracciamento.</strong> Le statistiche di lettura si basano su un'impronta
        giornaliera non reversibile: nessun cookie, nessun indirizzo IP conservato, nessun profilo.
        Chi legge un blog non riceve nemmeno un cookie. I dettagli sono
        nell'<a href="<?= e(Url::to('/privacy')) ?>">informativa</a>.</li>
    <li><strong>Pagine leggere.</strong> Nessun framework lato client, nessun font esterno, nessuna
        risorsa caricata da altri domini. Le pagine si aprono su una connessione lenta e si leggono
        su uno schermo piccolo.</li>
    <li><strong>Markdown.</strong> Il testo si scrive in Markdown, con tabelle, note a piè di pagina,
        blocchi di codice e <a href="<?= e(Url::to('/aiuto/markdown')) ?>">direttive</a> per inserire
        elenchi di articoli dove servono.</li>
    <li><strong>I contenuti restano tuoi.</strong> Puoi esportare tutto in file Markdown, collegare
        un tuo dominio e cancellare l'account con tutto il suo contenuto quando vuoi.</li>
  </ul>

  <h2>Registrazione aperta, indicizzazione no</h2>
  <p>
    Chiunque può aprire un blog senza chiedere il permesso: il blog è online e leggibile da subito.
    I blog appena creati però restano fuori dagli indici dei motori di ricerca e dalla
    <a href="<?= e(Url::to('/esplora')) ?>">vetrina</a> finché un moderatore non li approva. È il
    compromesso che permette di tenere aperta la registrazione senza diventare un deposito di spam:
    chi apre un blog per riempirlo di link non ottiene la visibilità che cerca, chi ci scrive
    davvero aspetta qualche ora.
  </p>

  <h2>Software</h2>
  <p>
    <?= e($siteName) ?> è scritto in PHP senza framework né dipendenze esterne, gira su un hosting
    condiviso qualunque con PHP 8.1 e MySQL, ed è software libero: puoi leggerne il codice,
    installarne una copia tua e proporre modifiche.
    Versione in esecuzione: <code><?= e($version) ?></code>.
  </p>
  <p>
    <a class="pf-button" href="<?= e((string) Config::get('site.source_url', 'https://noblogs.dev')) ?>" rel="noopener">Codice sorgente</a>
  </p>

  <h2>Contatti</h2>
  <?php if ($contact !== ''): ?>
    <p>
      Per segnalazioni di abuso, richieste sui tuoi dati o qualsiasi altra cosa:
      <a href="mailto:<?= e($contact) ?>"><?= e($contact) ?></a>.
    </p>
  <?php else: ?>
    <p>
      Chi gestisce questa installazione non ha indicato un indirizzo di contatto pubblico.
    </p>
  <?php endif; ?>
</div>
<?php
$this->end();
echo $this->slot('content'); // vedi la nota sullo slot in layouts/platform.php
