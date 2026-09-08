<?php

declare(strict_types=1);

namespace Noblogs\Controllers\Platform;

use Noblogs\Core\Auth;
use Noblogs\Core\Config;
use Noblogs\Core\Controller;
use Noblogs\Core\Response;
use Noblogs\Core\Url;
use Noblogs\Markdown\Parser;
use Noblogs\Markdown\Sanitizer;

/**
 * Pagine informative della piattaforma, robots.txt e sitemap del dominio
 * principale, più il 404 che risponde a tutto ciò che non è una rotta.
 *
 * La guida al markdown non descrive la sintassi a parole: gli esempi vengono
 * resi con lo stesso parser che rende gli articoli, quindi la pagina non può
 * raccontare un comportamento che il codice non ha.
 */
final class PageController extends Controller
{
    /** Secondi di cache nel browser per le pagine informative. */
    private const BROWSER_CACHE = 60;

    public function about(): Response
    {
        return $this->cached($this->view('platform/about', [
            'pageTitle'   => __('platform.about.title'),
            'description' => (string) Config::get('site.tagline', ''),
            'bodyClass'   => 'page about',
            'canonical'   => Url::platform('/informazioni'),
            'contact'     => (string) Config::get('site.contact_email', ''),
            'version'     => NOBLOGS_VERSION,
        ]));
    }

    public function privacy(): Response
    {
        return $this->cached($this->view('platform/privacy', [
            'pageTitle'   => __('platform.privacy.title'),
            'bodyClass'   => 'page privacy',
            'canonical'   => Url::platform('/privacy'),
            'contact'     => (string) Config::get('site.contact_email', ''),
            'domain'      => Url::mainDomain(),
            // Giorni di conservazione delle statistiche: 0 significa che
            // l'installazione non le cancella mai.
            'retention'   => (int) Config::get('limits.analytics_retention', 365),
            'verifyEmail' => (bool) Config::get('security.require_email_verification', true),
        ]));
    }

    public function terms(): Response
    {
        return $this->cached($this->view('platform/terms', [
            'pageTitle' => __('platform.terms.title'),
            'bodyClass' => 'page terms',
            'canonical' => Url::platform('/termini'),
            'contact'   => (string) Config::get('site.contact_email', ''),
            'review'    => (bool) Config::get('security.require_blog_review', true),
        ]));
    }

    public function help(): Response
    {
        return $this->cached($this->view('platform/help', [
            'pageTitle'      => __('platform.help.title'),
            'bodyClass'      => 'page help',
            'canonical'      => Url::platform('/aiuto'),
            'domain'         => Url::mainDomain(),
            'pathRouting'    => Config::get('routing.mode') === 'path',
            'blogsPerUser'   => (int) Config::get('limits.blogs_per_user', 3),
            'uploadMax'      => self::megabytes((int) Config::get('limits.upload_max_bytes', 0)),
            'storagePerBlog' => self::megabytes((int) Config::get('limits.storage_per_blog', 0)),
            'contact'        => (string) Config::get('site.contact_email', ''),
        ]));
    }

    public function markdown(): Response
    {
        return $this->cached($this->view('platform/markdown', [
            'pageTitle'  => __('platform.markdown.title'),
            'bodyClass'  => 'page markdown-guide',
            'canonical'  => Url::platform('/aiuto/markdown'),
            'sections'   => $this->guideSections(),
            'directives' => self::directives(),
            'filters'    => self::directiveFilters(),
        ]));
    }

    // -----------------------------------------------------------------------
    // robots.txt e sitemap del dominio principale
    // -----------------------------------------------------------------------

    public function robots(): Response
    {
        $body = "User-agent: *\nAllow: /\n\n"
            // Pagine senza contenuto proprio o riservate: non c'è ragione di
            // farle scandire, e i moduli non vanno indicizzati.
            . "Disallow: /accedi\n"
            . "Disallow: /esci\n"
            . "Disallow: /verifica-email\n"
            . "Disallow: /password\n"
            . "Disallow: /dashboard\n"
            . "Disallow: /admin\n"
            . "Disallow: /esplora/cerca\n"
            . "Disallow: /esplora/caso\n"
            . "Disallow: /esplora/caso-blog\n"
            . "Disallow: /install\n"
            . "\nSitemap: " . Url::platform('/sitemap.xml') . "\n";

        return Response::text($body)->cachePublic(3600, 'platform-robots');
    }

    public function sitemap(): Response
    {
        $paths = ['/', '/esplora', '/informazioni', '/aiuto', '/aiuto/markdown', '/privacy', '/termini', '/registrati'];

        // Le pagine successive della vetrina entrano nella sitemap solo se ci
        // sono davvero articoli che le riempiono, e non oltre le prime dieci.
        $pages = min(10, (int) ceil(DiscoverController::showcaseTotal() / 25));
        for ($page = 2; $page <= $pages; $page++) {
            $paths[] = '/esplora?pagina=' . $page;
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($paths as $path) {
            $xml .= '  <url><loc>'
                . htmlspecialchars(Url::platform($path), ENT_XML1 | ENT_QUOTES, 'UTF-8')
                . '</loc></url>' . "\n";
        }

        return Response::xml($xml . '</urlset>' . "\n")->cachePublic(3600, 'platform-sitemap');
    }

    /** Qualunque percorso della piattaforma che non corrisponda a una rotta. */
    public function missing(): Response
    {
        return $this->notFound()->noCache();
    }

    // -----------------------------------------------------------------------
    // Guida al markdown
    // -----------------------------------------------------------------------

    /**
     * Sezioni della guida. Ogni esempio viene reso qui e non nella vista, così
     * la pagina mostra il risultato reale del parser e non una sua imitazione.
     *
     * @return list<array{id:string,title:string,note:string,
     *                    examples:list<array{source:string,html:string}>}>
     */
    private function guideSections(): array
    {
        $sections = [];
        foreach (self::guideData() as $section) {
            $examples = [];
            foreach ($section['examples'] as $source) {
                $examples[] = ['source' => $source, 'html' => $this->renderExample($source)];
            }
            $sections[] = [
                'id'       => $section['id'],
                'title'    => $section['title'],
                'note'     => $section['note'],
                'examples' => $examples,
            ];
        }

        return $sections;
    }

    /**
     * Rende un esempio con la stessa catena di un blog qualunque: il parser
     * accetta l'HTML scritto a mano e la bonifica decide cosa ne resta. È la
     * configurazione predefinita (`allow_raw_html` disattivo), quella che vale
     * per tutti i blog tranne quelli esentati dall'amministrazione.
     */
    private function renderExample(string $markdown): string
    {
        return (new Sanitizer())->clean((new Parser(true))->text($markdown));
    }

    /**
     * @return list<array{id:string,title:string,note:string,examples:list<string>}>
     */
    private static function guideData(): array
    {
        return [
            [
                'id' => 'paragrafi',
                'title' => 'Paragrafi e ritorni a capo',
                'note' => 'Una riga vuota separa due paragrafi. Un Invio singolo, dentro lo stesso '
                    . 'paragrafo, va a capo (come in un editor).',
                'examples' => [
                    "Questo è un paragrafo: un a capo singolo\nresta visibile, non si fonde in uno spazio.\n\n"
                    . "Una riga vuota comincia un paragrafo nuovo.",
                ],
            ],
            [
                'id' => 'titoli',
                'title' => 'Titoli',
                'note' => 'Da uno a sei cancelletti. Ogni titolo riceve un identificatore ricavato dal '
                    . 'testo, che serve per i collegamenti interni (#il-mio-titolo) e per la direttiva {{ indice }}. '
                    . 'I titoli di primo e secondo livello si possono scrivere anche sottolineandoli.',
                'examples' => [
                    "# Titolo principale\n\n## Sezione\n\n### Sottosezione\n\n###### Sesto e ultimo livello",
                    "Titolo sottolineato\n===================\n\nSezione sottolineata\n--------------------",
                ],
            ],
            [
                'id' => 'enfasi',
                'title' => 'Corsivo, grassetto e compagnia',
                'note' => 'Il trattino basso in mezzo a una parola non crea corsivo, così i nomi come '
                    . 'nome_della_variabile restano leggibili.',
                'examples' => [
                    "*corsivo* oppure _corsivo_\n\n"
                    . "**grassetto** oppure __grassetto__\n\n"
                    . "***grassetto e corsivo insieme***\n\n"
                    . "~~cancellato~~ e ==evidenziato==\n\n"
                    . "H~2~O ha un pedice, il 6^o^ posto ha un apice.\n\n"
                    . "`codice_inline()` non viene interpretato.\n\n"
                    . "Un nome_con_trattini_bassi resta intatto.",
                ],
            ],
            [
                'id' => 'caratteri',
                'title' => 'Caratteri speciali e tipografia',
                'note' => 'Una barra rovesciata davanti a un carattere di punteggiatura lo rende letterale. '
                    . 'Alcune sequenze diventano il simbolo corrispondente, e le virgolette diritte diventano curve.',
                'examples' => [
                    "\\*questo non è corsivo\\* e \\_nemmeno questo\\_\n\n"
                    . "(c) (r) (tm) +- ... -- ---\n\n"
                    . "Le \"virgolette doppie\" e quelle 'singole' vengono curvate.\n\n"
                    . "Le entità HTML come &copy; &rarr; &#8212; passano intatte.",
                ],
            ],
            [
                'id' => 'elenchi',
                'title' => 'Elenchi',
                'note' => 'Puntati con -, * o +; numerati con "1." oppure "1)". Per annidare si indenta di '
                    . 'due spazi. Cambiare simbolo comincia un elenco nuovo. Le caselle da spuntare si '
                    . 'scrivono con [ ] e [x] e nella pagina non sono cliccabili.',
                'examples' => [
                    "- primo elemento\n- secondo elemento\n  - annidato\n  - un altro annidato\n- terzo elemento",
                    "1. primo passo\n2. secondo passo\n   1. dettaglio\n3. terzo passo\n\n"
                    . "5) si può anche partire da un altro numero\n6) e continuare",
                    "- [x] cosa già fatta\n- [ ] cosa da fare",
                ],
            ],
            [
                'id' => 'citazioni',
                'title' => 'Citazioni e riquadri',
                'note' => 'Le citazioni possono contenere altri blocchi, elenchi compresi. Un riquadro si apre '
                    . 'con [!NOTE], [!TIP], [!WARNING], [!DANGER] oppure [!INFO]; se non gli si dà un titolo, '
                    . 'ne riceve uno predefinito.',
                'examples' => [
                    "> Una citazione può continuare\n> su più righe.\n>\n> E contenere più paragrafi.",
                    "> [!NOTE]\n> Un riquadro con il titolo predefinito.\n\n"
                    . "> [!WARNING] Attenzione ai link accorciati\n> Il titolo del riquadro si può scrivere di seguito al tipo.",
                ],
            ],
            [
                'id' => 'codice',
                'title' => 'Codice',
                'note' => 'I blocchi si delimitano con tre o più apici inversi (oppure tre tildi) e si può '
                    . 'indicare il linguaggio, che diventa una classe CSS sul tag <code>. '
                    . 'I blocchi indentati di quattro spazi non sono supportati: nella pratica nascono per '
                    . 'sbaglio indentando un elenco.',
                'examples' => [
                    "Il codice breve va fra apici inversi: `array_map()`.\n\n"
                    . "```php\nfunction saluta(string \$nome): string\n{\n    return \"Ciao \$nome\";\n}\n```\n\n"
                    . "~~~\nUn blocco senza linguaggio, delimitato da tildi.\n~~~",
                ],
            ],
            [
                'id' => 'righe',
                'title' => 'Righe orizzontali',
                'note' => 'Tre o più trattini, asterischi o trattini bassi su una riga da soli. '
                    . 'Attenzione: dei trattini subito sotto un paragrafo diventano un titolo sottolineato, '
                    . 'non una riga.',
                'examples' => [
                    "Primo blocco.\n\n---\n\nSecondo blocco.\n\n***\n\nTerzo blocco.",
                ],
            ],
            [
                'id' => 'tabelle',
                'title' => 'Tabelle',
                'note' => 'La riga di separazione dichiara l\'allineamento con i due punti. Le righe più corte '
                    . 'o più lunghe dell\'intestazione vengono pareggiate. Una barra verticale dentro una cella '
                    . 'si scrive \\|.',
                'examples' => [
                    "| Voce      | Quantità | Stato    |\n"
                    . "|:----------|---------:|:--------:|\n"
                    . "| Articoli  |      128 | pubblici |\n"
                    . "| Bozze     |        3 | private  |\n"
                    . "| Pagine    |        7 | pubblici |",
                ],
            ],
            [
                'id' => 'definizioni',
                'title' => 'Elenchi di definizioni',
                'note' => 'Il termine sulla prima riga, la definizione sulla riga successiva introdotta dai '
                    . 'due punti. Un termine può avere più definizioni.',
                'examples' => [
                    "Markdown\n: Un modo di scrivere testo formattato usando solo caratteri comuni.\n\n"
                    . "Direttiva\n: Un segnaposto fra doppie graffe.\n: Viene sostituito quando la pagina viene generata.",
                ],
            ],
            [
                'id' => 'link',
                'title' => 'Collegamenti',
                'note' => 'Oltre alla forma normale ci sono i riferimenti, comodi quando lo stesso indirizzo '
                    . 'ricorre più volte, e gli indirizzi automatici. Il prefisso tab: apre il collegamento in '
                    . 'una scheda nuova. Gli schemi pericolosi (javascript:, data: fuori dalle immagini) '
                    . 'vengono bloccati.',
                'examples' => [
                    "[un collegamento](https://example.org)\n\n"
                    . "[con un titolo](https://example.org \"Compare passando il puntatore\")\n\n"
                    . "[a un altro tuo articolo](/il-mio-articolo/)\n\n"
                    . "[in una scheda nuova](tab:https://example.org)\n\n"
                    . "<https://example.org> e anche https://example.org da solo\n\n"
                    . "<posta@example.org>\n\n"
                    . "[con riferimento][esempio] e [un altro][esempio]\n\n"
                    . "[esempio]: https://example.org \"Titolo facoltativo\"",
                ],
            ],
            [
                'id' => 'immagini',
                'title' => 'Immagini',
                'note' => 'Come i collegamenti, ma con un punto esclamativo davanti. Il testo fra parentesi '
                    . 'quadre è l\'alternativa testuale: serve a chi usa uno screen reader e compare quando '
                    . 'l\'immagine non si carica — come negli esempi qui sotto, dove il file non esiste.',
                'examples' => [
                    "![Una fotografia del mare](/media/mare.jpg)\n\n"
                    . "![Con didascalia al passaggio](/media/mare.jpg \"Liguria, 2024\")",
                ],
            ],
            [
                'id' => 'note',
                'title' => 'Note a piè di pagina',
                'note' => 'Il rimando nel testo, la definizione dove capita: le note vengono raccolte e messe '
                    . 'in fondo alla pagina, numerate nell\'ordine in cui compaiono, con il collegamento per '
                    . 'tornare al punto di partenza.',
                'examples' => [
                    "Un'affermazione che ha bisogno di una fonte[^fonte], e una seconda nota[^altra].\n\n"
                    . "[^fonte]: La fonte, con il suo indirizzo: <https://example.org>\n"
                    . "[^altra]: Le note possono contenere più righe, se le righe successive\n"
                    . "  sono indentate di due spazi.",
                ],
            ],
            [
                'id' => 'html',
                'title' => 'HTML scritto a mano',
                'note' => 'Si può usare HTML in mezzo al markdown, ma passa da una lista bianca: restano i tag '
                    . 'di contenuto, spariscono script, style, i moduli e tutti i gestori di eventi, e gli '
                    . 'iframe sono ammessi solo verso una lista di servizi noti. Gli esempi qui sotto sono resi '
                    . 'con la bonifica attiva, come su qualsiasi blog.',
                'examples' => [
                    "Il testo può contenere <abbr title=\"HyperText Markup Language\">HTML</abbr> in linea, "
                    . "come <kbd>Ctrl</kbd>+<kbd>S</kbd>.\n\n"
                    . "<figure>\n<img src=\"/media/mare.jpg\" alt=\"Una fotografia del mare\">\n"
                    . "<figcaption>Una didascalia sotto l'immagine.</figcaption>\n</figure>\n\n"
                    . "<details>\n<summary>Un blocco che si apre e si chiude</summary>\n\n"
                    . "Il contenuto nascosto, che può essere **markdown**.\n\n</details>",
                    "<p onclick=\"alert(1)\">Il gestore di eventi viene rimosso.</p>\n\n"
                    . "<script>alert('questo sparisce del tutto')</script>",
                ],
            ],
        ];
    }

    /**
     * Le direttive riconosciute, nell'ordine in cui compaiono nel codice che
     * le risolve.
     *
     * @return list<array{code:string,aliases:string,effect:string,block:bool}>
     */
    private static function directives(): array
    {
        return [
            ['code' => '{{ posts }}', 'aliases' => 'post_list, elenco_post', 'block' => true,
             'effect' => 'L\'elenco dei tuoi articoli pubblicati, con la data e il titolo. Accetta i filtri elencati più sotto.'],
            ['code' => '{{ tags }}', 'aliases' => 'tag_cloud, nuvola_tag', 'block' => true,
             'effect' => 'L\'elenco dei tag usati nei tuoi articoli, ognuno collegato all\'elenco filtrato.'],
            ['code' => '{{ toc }}', 'aliases' => 'indice', 'block' => true,
             'effect' => 'L\'indice dei titoli della pagina, annidato secondo i livelli.'],
            ['code' => '{{ subscribe }}', 'aliases' => 'email_signup, iscrizione', 'block' => true,
             'effect' => 'Il modulo di iscrizione agli aggiornamenti. Non produce nulla se le iscrizioni sono disattivate.'],
            ['code' => '{{ search }}', 'aliases' => 'cerca', 'block' => true,
             'effect' => 'Il modulo di ricerca interno al blog.'],
            ['code' => '{{ archive }}', 'aliases' => 'archivio', 'block' => true,
             'effect' => 'Tutti gli articoli raggruppati per anno.'],
            ['code' => '{{ next_post }}', 'aliases' => 'post_successivo', 'block' => true,
             'effect' => 'Il collegamento all\'articolo successivo, se esiste.'],
            ['code' => '{{ previous_post }}', 'aliases' => 'post_precedente', 'block' => true,
             'effect' => 'Il collegamento all\'articolo precedente, se esiste.'],
            ['code' => '{{ post_nav }}', 'aliases' => '—', 'block' => true,
             'effect' => 'Precedente e successivo affiancati, per il fondo di un articolo.'],
            ['code' => '{{ blog_title }}', 'aliases' => 'titolo_blog', 'block' => false,
             'effect' => 'Il titolo del blog.'],
            ['code' => '{{ blog_description }}', 'aliases' => 'descrizione_blog', 'block' => false,
             'effect' => 'La descrizione del blog, o le prime righe della homepage se non l\'hai scritta.'],
            ['code' => '{{ blog_link }}', 'aliases' => 'indirizzo_blog', 'block' => false,
             'effect' => 'L\'indirizzo del blog.'],
            ['code' => '{{ blog_created }}', 'aliases' => 'blog_creato', 'block' => false,
             'effect' => 'La data di creazione del blog.'],
            ['code' => '{{ blog_last_posted }}', 'aliases' => 'ultimo_post', 'block' => false,
             'effect' => 'Quanto tempo è passato dall\'ultima pubblicazione, per esempio «3 giorni».'],
            ['code' => '{{ post_count }}', 'aliases' => 'numero_post', 'block' => false,
             'effect' => 'Il numero di articoli pubblicati.'],
            ['code' => '{{ post_title }}', 'aliases' => 'titolo_post', 'block' => false,
             'effect' => 'Il titolo dell\'articolo corrente.'],
            ['code' => '{{ post_description }}', 'aliases' => 'descrizione_post', 'block' => false,
             'effect' => 'La descrizione dell\'articolo corrente.'],
            ['code' => '{{ post_link }}', 'aliases' => 'indirizzo_post', 'block' => false,
             'effect' => 'L\'indirizzo dell\'articolo corrente.'],
            ['code' => '{{ post_date }}', 'aliases' => 'data_post', 'block' => false,
             'effect' => 'La data di pubblicazione dell\'articolo corrente.'],
            ['code' => '{{ post_updated }}', 'aliases' => 'post_aggiornato', 'block' => false,
             'effect' => 'La data dell\'ultima modifica dell\'articolo corrente.'],
            ['code' => '{{ year }}', 'aliases' => 'anno', 'block' => false,
             'effect' => 'L\'anno corrente, comodo per la riga del copyright nel piè di pagina.'],
        ];
    }

    /**
     * @return list<array{code:string,effect:string}>
     */
    private static function directiveFilters(): array
    {
        return [
            ['code' => '{{ posts|limit:5 }}', 'effect' => 'Quanti articoli mostrare, al massimo 200. Sinonimo: limite. Senza filtro sono 50.'],
            ['code' => '{{ posts|order:asc }}', 'effect' => 'Dal più vecchio al più recente. Sinonimo: ordine.'],
            ['code' => '{{ posts|pages }}', 'effect' => 'Include anche le pagine, non solo gli articoli. Sinonimo: pagine.'],
            ['code' => '{{ posts|tag:appunti }}', 'effect' => 'Solo gli articoli con quel tag; più tag separati da virgola, con il meno davanti per escluderli (tag:appunti,-privato).'],
            ['code' => '{{ posts|from:2024-01-01|to:2024-12-31 }}', 'effect' => 'Solo un intervallo di date. Sinonimi: da e a.'],
            ['code' => '{{ posts|description:sì }}', 'effect' => 'Aggiunge la descrizione sotto ogni titolo. Sinonimo: descrizione.'],
            ['code' => '{{ posts|image:sì }}', 'effect' => 'Aggiunge l\'immagine dell\'articolo, se ne ha una. Sinonimo: immagine.'],
            ['code' => '{{ posts|date:no }}', 'effect' => 'Toglie la data dall\'elenco. Sinonimo: data.'],
            ['code' => '{{ tags|limit:20 }}', 'effect' => 'Quanti tag mostrare, al massimo 200.'],
            ['code' => '{{ toc|depth:2 }}', 'effect' => 'Fino a quale livello di titoli scendere, da 2 a 6. Sinonimo: profondita.'],
        ];
    }

    // -----------------------------------------------------------------------
    // Supporto
    // -----------------------------------------------------------------------

    /**
     * Le pagine informative sono uguali per tutti, ma il guscio mostra
     * «Dashboard» a chi è autenticato: si mettono in cache condivisa solo per
     * chi non ha una sessione.
     */
    /**
     * Cache delle pagine che montano il guscio della piattaforma.
     *
     * Niente cache condivisa, per due ragioni: il guscio mostra «Dashboard» a
     * chi è autenticato e può contenere un messaggio flash destinato a una
     * persona sola, e soprattutto il front controller apre una sessione su
     * ogni richiesta del dominio principale, quindi ogni risposta porta con sé
     * un Set-Cookie che in una cache condivisa diventerebbe una sessione in
     * comune fra visitatori diversi. Resta la cache privata del browser, con
     * una finestra corta: tanto basta per una ricarica o per il tasto indietro
     * senza mostrare troppo a lungo un guscio invecchiato.
     */
    private function cached(Response $response): Response
    {
        if (Auth::check()) {
            return $response->noCache();
        }

        return $response
            ->withHeader('Cache-Control', 'private, max-age=' . self::BROWSER_CACHE)
            ->withHeader('Vary', 'Cookie');
    }

    private static function megabytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '—';
        }

        return number_format($bytes / (1024 * 1024), 0, ',', '.') . ' MB';
    }
}
