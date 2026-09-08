<?php
/**
 * Guida introduttiva.
 *
 * @var \Noblogs\Core\View $this
 * @var string $domain
 * @var bool   $pathRouting
 * @var int    $blogsPerUser
 * @var string $uploadMax
 * @var string $storagePerBlog
 * @var string $contact
 */

use Noblogs\Core\Config;
use Noblogs\Core\Url;

$siteName = (string) ($siteName ?? Config::get('site.name', 'Noblogs'));
$example = $pathRouting ? $domain . '/ilmioblog/' : 'ilmioblog.' . $domain;

$this->layout('layouts/platform');
$this->start('content');
?>
<div class="pf-shell pf-prose">
  <h1>Aiuto</h1>

  <p class="pf-lead">
    Tutto quello che serve per cominciare, in una pagina. Per la sintassi del testo c'è la
    <a href="<?= e(Url::to('/aiuto/markdown')) ?>">guida al Markdown</a>, con gli esempi resi
    accanto al codice.
  </p>

  <nav class="pf-toc" aria-label="In questa pagina">
    <ol>
      <li><a href="#creare">Creare un blog</a></li>
      <li><a href="#scrivere">Scrivere un articolo</a></li>
      <li><a href="#pagine">Pagine, navigazione e homepage</a></li>
      <li><a href="#tema">Scegliere un tema</a></li>
      <li><a href="#indirizzi">Indirizzi, sottodomini e dominio proprio</a></li>
      <li><a href="#statistiche">Statistiche e apprezzamenti</a></li>
      <li><a href="#file">File e immagini</a></li>
      <li><a href="#esportare">Esportare e importare</a></li>
      <li><a href="#vetrina">Comparire nella vetrina</a></li>
    </ol>
  </nav>

  <h2 id="creare">Creare un blog</h2>
  <p>
    <a href="<?= e(Url::to('/registrati')) ?>">Registrandoti</a> crei insieme l'account e il tuo
    primo blog: indirizzo email, password, indirizzo del blog e titolo. Non c'è un secondo
    passaggio: appena finito, il blog è online.
  </p>
  <p>
    Riceverai un messaggio per confermare l'indirizzo email. Fallo: senza conferma non puoi creare
    altri blog né recuperare la password.
  </p>
  <p>
    Con un solo account puoi tenere fino a <strong><?= e((string) $blogsPerUser) ?> blog</strong>.
    Si aggiungono dalla dashboard, alla voce «Nuovo blog».
  </p>

  <h2 id="scrivere">Scrivere un articolo</h2>
  <p>
    Nella dashboard scegli il blog, poi «Articoli» e «Nuovo». Un articolo è fatto di tre cose:
    un titolo, un indirizzo (lo <em>slug</em>, che viene proposto a partire dal titolo) e il testo
    in Markdown.
  </p>
  <ul>
    <li><strong>Bozza o pubblicato.</strong> Una bozza la vedi solo tu; il link di anteprima ti
        permette di mostrarla a qualcuno prima di pubblicarla.</li>
    <li><strong>Data di pubblicazione.</strong> Se la metti nel futuro, l'articolo compare da
        quel momento. La data si scrive nel tuo fuso orario, quello che hai impostato
        nell'account.</li>
    <li><strong>Tag.</strong> Separati da virgola. Servono a raggruppare gli articoli e diventano
        filtri nell'elenco del tuo blog.</li>
    <li><strong>Descrizione e immagine.</strong> Sono i metadati che compaiono quando qualcuno
        condivide il link. Se non li scrivi, la descrizione viene ricavata dalle prime righe.</li>
  </ul>
  <p>
    Il testo si scrive in Markdown: titoli con i cancelletti, elenchi con i trattini, collegamenti
    fra quadre e tonde. Vedi la <a href="<?= e(Url::to('/aiuto/markdown')) ?>">guida completa</a>.
  </p>

  <h2 id="pagine">Pagine, navigazione e homepage</h2>
  <p>
    Una <strong>pagina</strong> si scrive come un articolo ma non entra nell'elenco cronologico né
    nei feed: è il posto giusto per «Chi sono», «Contatti» o un progetto.
  </p>
  <p>
    La <strong>homepage</strong> del blog è un testo in Markdown che scrivi tu, alla voce
    «Contenuto». Qui tornano utili le direttive: <code>{{ posts|limit:10 }}</code> inserisce
    l'elenco degli ultimi dieci articoli dove l'hai scritta. Se lasci la homepage vuota, il blog
    mostra direttamente l'elenco degli articoli.
  </p>
  <p>
    La <strong>barra di navigazione</strong> è una riga di Markdown: collegamenti uno accanto
    all'altro, per esempio <code>[Home](/) [Blog](/blog/) [Chi sono](/chi-sono/)</code>. Due righe
    diventano due gruppi di collegamenti.
  </p>

  <h2 id="tema">Scegliere un tema</h2>
  <p>
    Alla voce «Aspetto» trovi i temi disponibili: cambiano tipografia, larghezza della colonna e
    colori. Sono tutti pensati per essere leggibili e funzionano con il tema scuro del sistema.
  </p>
  <p>
    Sotto al tema puoi aggiungere <strong>CSS tuo</strong>, che si somma a quello del tema. Se
    vuoi partire da zero c'è l'opzione per sostituirlo del tutto: in quel caso la resa della pagina
    è interamente nelle tue mani. Il CSS viene incorporato nella pagina, quindi non aggiunge
    nessuna richiesta e non c'è un istante in cui la pagina appare senza formattazione.
  </p>

  <h2 id="indirizzi">Indirizzi, sottodomini e dominio proprio</h2>
  <p>
    L'indirizzo che scegli alla registrazione diventa il sottodominio del tuo blog:
    <code><?= e($example) ?></code>. Può contenere lettere minuscole, numeri e trattini singoli,
    da 3 a 63 caratteri, e non può cominciare o finire con un trattino. Alcuni nomi sono riservati
    al sistema (<code>www</code>, <code>admin</code>, <code>mail</code> e simili).
  </p>
  <?php if ($pathRouting): ?>
    <p>
      Questa installazione serve i blog dentro il percorso del dominio principale: il tuo blog è
      raggiungibile a <code><?= e($domain) ?>/ilmioblog/</code>.
    </p>
  <?php else: ?>
    <p>
      I blog restano raggiungibili anche come percorso del dominio principale
      (<code><?= e($domain) ?>/ilmioblog/</code>). Finché un blog non è approvato il terzo
      livello non è ancora attivo: risponde solo il percorso. Dopo l'approvazione funzionano
      entrambi, e l'indirizzo canonico — quello nei feed, nella sitemap e nei tag
      <code>canonical</code> — è il sottodominio.
    </p>
  <?php endif; ?>
  <p>
    L'elenco degli articoli sta su <code>/blog/</code>, e puoi spostarlo dove preferisci nelle
    impostazioni; <code>/blog/</code> continua a funzionare comunque, così i link già condivisi
    non si rompono.
  </p>
  <p>
    Se hai un <strong>dominio tuo</strong>, nelle impostazioni avanzate puoi collegarlo: fai
    puntare il DNS a questo server e scrivi il dominio nel campo apposito. Con e senza
    <code>www</code> portano allo stesso blog.
  </p>
  <p>
    Ogni blog ha un <strong>feed</strong> su <code>/feed/</code> (e risponde anche a
    <code>/rss</code>, <code>/atom</code>, <code>/feed.xml</code> e agli altri nomi che gli
    aggregatori provano), una <strong>sitemap</strong> su <code>/sitemap.xml</code> e un
    <code>robots.txt</code> che puoi modificare.
  </p>

  <h2 id="statistiche">Statistiche e apprezzamenti</h2>
  <p>
    Le statistiche contano le letture senza cookie e senza conservare indirizzi IP: vedi le letture
    per giorno, i visitatori distinti, gli articoli più letti e da dove arrivano i lettori. Non
    vedrai <em>chi</em> ha letto, perché quel dato non esiste da nessuna parte — come spieghiamo
    nell'<a href="<?= e(Url::to('/privacy')) ?>">informativa</a>. Puoi spegnerle del tutto nelle
    impostazioni del blog.
  </p>
  <p>
    Gli <strong>apprezzamenti</strong> permettono a un lettore di segnalare che un articolo gli è
    piaciuto senza registrarsi. Contano anche per l'ordine della vetrina. Si possono disattivare.
  </p>

  <h2 id="file">File e immagini</h2>
  <p>
    Alla voce «File» carichi immagini e allegati. Ogni file può pesare fino a
    <strong><?= e($uploadMax) ?></strong> e ogni blog ha
    <strong><?= e($storagePerBlog) ?></strong> di spazio. Le immagini troppo grandi vengono
    ridimensionate. Dopo il caricamento ti viene mostrato l'indirizzo da incollare nel Markdown:
    <code>![Descrizione](/media/…)</code>.
  </p>
  <p>
    Scrivi sempre una descrizione fra le quadre: la legge chi usa uno screen reader e compare
    quando l'immagine non si carica.
  </p>

  <h2 id="esportare">Esportare e importare</h2>
  <p>
    Alla voce «Esporta» scarichi tutto il blog: un file per articolo, in Markdown, con i metadati
    in testa fra due righe di trattini. È lo stesso formato che usano Jekyll, Hugo e Obsidian,
    quindi i tuoi testi si possono portare altrove senza conversioni.
  </p>
  <p>
    La voce «Importa» fa il contrario: accetta file Markdown con la stessa intestazione. Le chiavi
    riconosciute hanno tutte un sinonimo italiano — <code>titolo</code>, <code>data</code>,
    <code>tag</code>, <code>descrizione</code>, <code>bozza</code>, <code>pagina</code> — e le
    chiavi non riconosciute vengono segnalate senza bloccare l'importazione.
  </p>
  <p>
    Consiglio spassionato: esporta di tanto in tanto e tieni la copia da qualche parte. Vale per
    <?= e($siteName) ?> come per qualsiasi servizio.
  </p>

  <h2 id="vetrina">Comparire nella vetrina</h2>
  <p>
    La <a href="<?= e(Url::to('/esplora')) ?>">vetrina</a> raccoglie gli articoli dei blog ospitati
    qui. Perché un articolo compaia servono quattro cose:
  </p>
  <ol>
    <li>il blog è stato <strong>approvato da un moderatore</strong> (i blog nuovi aspettano);</li>
    <li>il blog ha la voce «Mostra nella vetrina» attiva nelle impostazioni;</li>
    <li>l'articolo ha la sua casella «In vetrina» attiva ed è pubblicato;</li>
    <li>l'articolo è lungo almeno un paio di paragrafi: quelli di poche righe restano fuori.</li>
  </ol>
  <p>
    Un blog che pubblica moltissimo in poche ore viene messo in pausa dalla vetrina, così un solo
    blog non occupa la pagina. L'ordine predefinito tiene conto degli apprezzamenti e
    dell'anzianità; con «Più recenti» e «A caso» si guarda la stessa raccolta in altri modi.
  </p>

  <?php if ($contact !== ''): ?>
    <h2>Qualcosa non torna?</h2>
    <p>
      Scrivi a <a href="mailto:<?= e($contact) ?>"><?= e($contact) ?></a>: rispondono persone.
    </p>
  <?php endif; ?>
</div>
<?php
$this->end();
echo $this->slot('content'); // vedi la nota sullo slot in layouts/platform.php
