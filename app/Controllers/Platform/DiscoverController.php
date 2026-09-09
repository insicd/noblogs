<?php

declare(strict_types=1);

namespace Noblogs\Controllers\Platform;

use Noblogs\Core\Auth;
use Noblogs\Core\Config;
use Noblogs\Core\Controller;
use Noblogs\Core\Database;
use Noblogs\Core\Response;
use Noblogs\Core\Url;
use Noblogs\Markdown\Cache;
use Noblogs\Models\Blog;
use Noblogs\Models\Post;
use Noblogs\Support\Dates;

/**
 * La vetrina: gli articoli pubblici dei blog ospitati qui.
 *
 * Comparire in vetrina non è automatico. Servono tre consensi indipendenti:
 * quello del moderatore (il blog è stato approvato), quello di chi tiene il
 * blog (`discoverable`) e quello di chi ha scritto l'articolo
 * (`make_discoverable`). A questi si aggiungono due filtri sulla qualità
 * dell'elenco: gli articoli molto corti restano fuori, e un blog che ha
 * pubblicato a raffica nelle ultime dodici ore non occupa la pagina.
 */
final class DiscoverController extends Controller
{
    private const PER_PAGE = 25;

    /** Tetto alle pagine: oltre, un robot scandirebbe elenchi senza fine. */
    private const MAX_PAGES = 100;

    /** Sotto questa lunghezza un articolo non è un articolo. */
    private const MIN_LENGTH = 150;

    /** Antiflood: blog che pubblicano più di così in 12 ore restano fuori. */
    private const MAX_POSTS_12H = 5;

    private const MAX_TERMS = 8;

    private const FEED_ITEMS = 30;

    /** Secondi di cache nel browser per gli elenchi della vetrina. */
    private const BROWSER_CACHE = 60;

    // -----------------------------------------------------------------------
    // Elenco
    // -----------------------------------------------------------------------

    public function index(): Response
    {
        $order = self::normalizeOrder($this->request->query('ordina') ?? '');
        $lang = self::normalizeLang($this->request->query('lingua') ?? '');
        $page = max(1, min(self::MAX_PAGES, $this->request->int('pagina', 1)));

        // Con l'ordine casuale la paginazione non ha senso: la seconda pagina
        // sarebbe un altro sorteggio, con gli stessi articoli possibili.
        if ($order === 'caso') {
            $page = 1;
        }

        $total = self::showcaseTotal(['lang' => $lang]);
        $lastPage = max(1, min(self::MAX_PAGES, (int) ceil($total / self::PER_PAGE)));

        if ($page > $lastPage && $page > 1) {
            return $this->redirect(Url::to('/esplora'));
        }

        $entries = self::showcase([
            'order'  => $order,
            'lang'   => $lang,
            'limit'  => self::PER_PAGE,
            'offset' => ($page - 1) * self::PER_PAGE,
        ]);

        return $this->cached($this->view('platform/discover', [
            'pageTitle'   => __('discover.title'),
            'description' => __('discover.intro'),
            'bodyClass'   => 'discover',
            'canonical'   => Url::platform('/esplora'),
            // Solo la prima pagina senza filtri va negli indici: le altre sono
            // ricombinazioni degli stessi articoli.
            'indexable'   => $page === 1 && $lang === null && $order === 'score',
            'entries'     => $entries,
            'order'       => $order,
            'lang'        => $lang,
            'languages'   => self::languages(),
            'currentPage' => $page,
            'lastPage'    => $order === 'caso' ? 1 : $lastPage,
            'total'       => $total,
        ]));
    }

    // -----------------------------------------------------------------------
    // Ricerca
    // -----------------------------------------------------------------------

    public function search(): Response
    {
        $query = trim($this->request->query('q') ?? '');
        $entries = [];

        if (mb_strlen($query) >= 2) {
            $entries = self::showcase([
                'search' => $query,
                'lang'   => self::normalizeLang($this->request->query('lingua') ?? ''),
                'limit'  => self::PER_PAGE * 2,
            ]);
        }

        return $this->view('platform/search', [
            'pageTitle' => $query !== ''
                ? __('discover.search_results', ['query' => $query])
                : __('discover.search_title'),
            'bodyClass' => 'discover search',
            // Le pagine di ricerca sono infinite e non hanno contenuto proprio.
            'indexable' => false,
            'query'     => $query,
            'entries'   => $entries,
        ])->noIndex();
    }

    // -----------------------------------------------------------------------
    // Feed della vetrina
    // -----------------------------------------------------------------------

    public function feed(): Response
    {
        $entries = self::showcase([
            'order' => self::normalizeOrder($this->request->query('ordina') ?? ''),
            'lang'  => self::normalizeLang($this->request->query('lingua') ?? ''),
            'limit' => self::FEED_ITEMS,
        ]);

        // Il feed è identico per tutti e non contiene niente di personale:
        // qui la cache condivisa ha senso.
        return Response::xml($this->renderAtom($entries))
            ->withHeader('Content-Type', 'application/atom+xml; charset=UTF-8')
            ->cachePublic((int) Config::get('site.cache_seconds', 300), 'discover-feed');
    }

    public function random(): Response
    {
        $entries = self::showcase(['order' => 'caso', 'limit' => 1]);

        if ($entries === []) {
            return $this->redirect(Url::to('/esplora'));
        }

        return $this->redirect(
            Url::post($entries[0]['blog'], $entries[0]['post']->slug)
        )->noCache()->noIndex();
    }

    public function randomBlog(): Response
    {
        $row = Database::instance()->fetch(
            'SELECT b.id, b.subdomain, b.domain, b.title
             FROM {{blogs}} b
             INNER JOIN {{users}} u ON u.id = b.user_id
             WHERE b.reviewed = 1 AND b.hidden = 0 AND b.flagged = 0 AND b.discoverable = 1
               AND u.is_active = 1
               AND EXISTS (
                   SELECT 1 FROM {{posts}} p
                   WHERE p.blog_id = b.id
                     AND p.is_page = 0 AND p.is_published = 1 AND p.hidden = 0
                     AND p.make_discoverable = 1
                     AND p.published_at <= UTC_TIMESTAMP()
                     AND p.content_length >= ' . self::MIN_LENGTH . '
               )
             ORDER BY RAND()
             LIMIT 1'
        );

        if ($row === null) {
            return $this->redirect(Url::to('/esplora'));
        }

        return $this->redirect(
            Url::blogRoot(Blog::hydrate($row))
        )->noCache()->noIndex();
    }

    // -----------------------------------------------------------------------
    // Interrogazione della vetrina (condivisa con la pagina di ingresso)
    // -----------------------------------------------------------------------

    /**
     * Articoli idonei alla vetrina, ognuno con il blog che lo ospita.
     *
     * @param array{order?:string,lang?:?string,limit?:int,offset?:int,search?:string} $options
     * @return list<array{post:Post,blog:Blog}>
     */
    public static function showcase(array $options = []): array
    {
        $params = [];
        $where = self::conditions($options, $params);
        $limit = max(1, min(200, (int) ($options['limit'] ?? self::PER_PAGE)));
        $offset = max(0, (int) ($options['offset'] ?? 0));

        $sql = 'SELECT p.*,
                       b.id AS blog_ref_id, b.user_id AS blog_user_id,
                       b.subdomain AS blog_subdomain, b.domain AS blog_domain,
                       b.title AS blog_title, b.lang AS blog_lang,
                       b.date_format AS blog_date_format
                FROM {{posts}} p
                INNER JOIN {{blogs}} b ON b.id = p.blog_id
                INNER JOIN {{users}} u ON u.id = b.user_id
                WHERE ' . $where
            . ' ORDER BY ' . self::orderBy($options, $params)
            . ' LIMIT ' . $limit . ' OFFSET ' . $offset;

        $entries = [];
        foreach (Database::instance()->fetchAll($sql, $params) as $row) {
            $entries[] = [
                'post' => Post::hydrate($row),
                'blog' => Blog::hydrate([
                    'id'          => (int) $row['blog_ref_id'],
                    'user_id'     => (int) $row['blog_user_id'],
                    'subdomain'   => (string) $row['blog_subdomain'],
                    'domain'      => $row['blog_domain'],
                    'title'       => (string) $row['blog_title'],
                    'lang'        => (string) $row['blog_lang'],
                    'date_format' => (string) $row['blog_date_format'],
                ]),
            ];
        }

        return $entries;
    }

    /** @param array{lang?:?string,search?:string} $options */
    public static function showcaseTotal(array $options = []): int
    {
        $params = [];
        $where = self::conditions($options, $params);

        return (int) Database::instance()->fetchColumn(
            'SELECT COUNT(*)
             FROM {{posts}} p
             INNER JOIN {{blogs}} b ON b.id = p.blog_id
             INNER JOIN {{users}} u ON u.id = b.user_id
             WHERE ' . $where,
            $params
        );
    }

    /**
     * Lingue presenti in vetrina, con il numero di articoli. È un conteggio
     * su tutta la tabella: si tiene in cache un'ora come le altre statistiche.
     *
     * @return list<array{code:string,total:int}>
     */
    public static function languages(): array
    {
        $cached = Cache::get('platform/discover-langs');
        if ($cached !== null) {
            $decoded = json_decode($cached, true);
            if (is_array($decoded)) {
                return array_values(array_map(
                    static fn(array $row): array => [
                        'code'  => (string) ($row['code'] ?? ''),
                        'total' => (int) ($row['total'] ?? 0),
                    ],
                    array_filter($decoded, 'is_array')
                ));
            }
        }

        $params = [];
        $rows = Database::instance()->fetchAll(
            'SELECT LEFT(COALESCE(NULLIF(p.lang, \'\'), b.lang), 2) AS code, COUNT(*) AS total
             FROM {{posts}} p
             INNER JOIN {{blogs}} b ON b.id = p.blog_id
             INNER JOIN {{users}} u ON u.id = b.user_id
             WHERE ' . self::conditions([], $params) . '
             GROUP BY code
             HAVING code <> \'\'
             ORDER BY total DESC, code ASC
             LIMIT 12',
            $params
        );

        $languages = array_map(static fn(array $row): array => [
            'code'  => (string) $row['code'],
            'total' => (int) $row['total'],
        ], $rows);

        Cache::put('platform/discover-langs', json_encode($languages) ?: '[]', 3600);

        return $languages;
    }

    // -----------------------------------------------------------------------
    // Costruzione della query
    // -----------------------------------------------------------------------

    /**
     * @param array{lang?:?string,search?:string} $options
     * @param array<string,mixed>                 $params
     */
    private static function conditions(array $options, array &$params): string
    {
        $sql = 'b.reviewed = 1 AND b.hidden = 0 AND b.flagged = 0 AND b.discoverable = 1
                AND u.is_active = 1
                AND p.is_page = 0 AND p.is_published = 1 AND p.hidden = 0
                AND p.make_discoverable = 1
                AND p.published_at <= UTC_TIMESTAMP()
                AND p.content_length >= ' . self::MIN_LENGTH . '
                AND b.posts_last_12h <= ' . self::MAX_POSTS_12H;

        $lang = $options['lang'] ?? null;
        if (is_string($lang) && $lang !== '') {
            // La lingua dell'articolo prevale su quella del blog, che è il
            // valore di ripiego quando il post non la dichiara.
            $sql .= ' AND LEFT(COALESCE(NULLIF(p.lang, \'\'), b.lang), 2) = :lang';
            $params['lang'] = $lang;
        }

        $search = trim((string) ($options['search'] ?? ''));
        if ($search !== '') {
            foreach (self::terms($search) as $index => $term) {
                // In AND: tutte le parole devono comparire da qualche parte.
                // I quattro campi si cercano concatenati invece di ripetere lo
                // stesso segnaposto quattro volte, perché le prepared statement
                // native di MySQL accettano ogni nome una sola volta per query.
                // I termini non contengono spazi, quindi nessuno può
                // corrispondere a cavallo del separatore.
                $sql .= " AND CONCAT_WS(' ', p.title, p.tags, b.subdomain, b.title) LIKE :term$index";
                $params["term$index"] = '%' . self::escapeLike($term) . '%';
            }
        }

        return $sql;
    }

    /**
     * @param array{order?:string,search?:string} $options
     * @param array<string,mixed>                 $params
     */
    private static function orderBy(array $options, array &$params): string
    {
        $search = trim((string) ($options['search'] ?? ''));
        if ($search !== '') {
            // Pertinenza: prima il blog chiamato esattamente così, poi il
            // titolo identico, poi il titolo che comincia con la query, e a
            // parità di tutto questo il punteggio della vetrina.
            // Due segnaposto distinti con lo stesso valore: vedi la nota in
            // conditions() sui nomi ripetuti.
            $params['exact_sub'] = mb_strtolower($search);
            $params['exact_title'] = mb_strtolower($search);
            $params['prefix'] = self::escapeLike($search) . '%';

            return '(b.subdomain = :exact_sub) DESC, (LOWER(p.title) = :exact_title) DESC,'
                . ' (p.title LIKE :prefix) DESC, p.score DESC, p.id DESC';
        }

        return match ($options['order'] ?? 'score') {
            'recenti' => 'p.published_at DESC, p.id DESC',
            'caso'    => 'RAND()',
            default   => 'p.score DESC, p.id DESC',
        };
    }

    /** @return list<string> */
    private static function terms(string $query): array
    {
        $terms = [];
        foreach (preg_split('/\s+/u', $query) ?: [] as $term) {
            $term = trim($term);
            if (mb_strlen($term) >= 2) {
                $terms[] = mb_substr($term, 0, 60);
            }
        }

        return array_slice($terms, 0, self::MAX_TERMS);
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private static function normalizeOrder(string $order): string
    {
        return in_array($order, ['recenti', 'caso'], true) ? $order : 'score';
    }

    private static function normalizeLang(string $lang): ?string
    {
        $lang = mb_strtolower(trim($lang));

        return preg_match('/^[a-z]{2}$/', $lang) === 1 ? $lang : null;
    }

    // -----------------------------------------------------------------------
    // Presentazione
    // -----------------------------------------------------------------------

    /**
     * Solo cache privata del browser: il guscio dipende da chi guarda e ogni
     * risposta del dominio principale porta un Set-Cookie di sessione, che in
     * una cache condivisa diventerebbe una sessione in comune. Il feed, che
     * non monta il guscio, usa invece la cache condivisa.
     */
    private function cached(Response $response): Response
    {
        if (Auth::check()) {
            return $response->noCache();
        }

        return $response
            ->withHeader('Cache-Control', 'private, max-age=' . self::BROWSER_CACHE)
            ->withHeader('Vary', 'Cookie, Accept-Language');
    }

    /** @param list<array{post:Post,blog:Blog}> $entries */
    private function renderAtom(array $entries): string
    {
        $self = Url::platform('/esplora/feed');
        $site = (string) Config::get('site.name', 'Noblogs');
        $updated = $entries !== [] ? $entries[0]['post']->updatedAt() : Dates::now();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<feed xmlns="http://www.w3.org/2005/Atom">' . "\n"
            . '  <title>' . self::x($site . ' — ' . __('discover.title')) . '</title>' . "\n"
            . '  <subtitle>' . self::x(__('discover.intro')) . '</subtitle>' . "\n"
            . '  <link href="' . self::x($self) . '" rel="self" type="application/atom+xml"/>' . "\n"
            . '  <link href="' . self::x(Url::platform('/esplora')) . '" rel="alternate" type="text/html"/>' . "\n"
            . '  <id>' . self::x($self) . '</id>' . "\n"
            . '  <updated>' . self::x(Dates::iso($updated)) . '</updated>' . "\n"
            . '  <generator uri="https://noblogs.dev">Noblogs</generator>' . "\n";

        foreach ($entries as $entry) {
            $post = $entry['post'];
            $blog = $entry['blog'];
            $url = Url::post($blog, $post->slug);

            // Nel feed della vetrina va solo il riassunto: il testo completo
            // appartiene al feed del blog che lo ha pubblicato.
            $xml .= '  <entry>' . "\n"
                . '    <title>' . self::x($post->title) . '</title>' . "\n"
                . '    <link href="' . self::x($url) . '" rel="alternate" type="text/html"/>' . "\n"
                . '    <id>' . self::x($url) . '</id>' . "\n"
                . '    <published>' . self::x(Dates::iso($post->publishedAt())) . '</published>' . "\n"
                . '    <updated>' . self::x(Dates::iso($post->updatedAt())) . '</updated>' . "\n"
                . '    <author><name>' . self::x($blog->title) . '</name>'
                . '<uri>' . self::x(Url::blogRoot($blog)) . '</uri></author>' . "\n"
                . '    <summary>' . self::x($post->description()) . '</summary>' . "\n";

            foreach ($post->tagList() as $tag) {
                $xml .= '    <category term="' . self::x($tag) . '"/>' . "\n";
            }

            $xml .= '  </entry>' . "\n";
        }

        return $xml . '</feed>' . "\n";
    }

    private static function x(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
