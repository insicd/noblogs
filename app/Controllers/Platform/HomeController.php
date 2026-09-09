<?php

declare(strict_types=1);

namespace Noblogs\Controllers\Platform;

use Noblogs\Core\Auth;
use Noblogs\Core\Config;
use Noblogs\Core\Controller;
use Noblogs\Core\Database;
use Noblogs\Core\Response;
use Noblogs\Core\Session;
use Noblogs\Core\Url;
use Noblogs\Markdown\Cache;

/**
 * Pagina di ingresso della piattaforma.
 *
 * Spiega in poche righe cos'è Noblogs e mette a portata di mano il modulo
 * di registrazione, oppure il collegamento al pannello se chi visita è già
 * autenticato. I numeri mostrati sono quelli veri, calcolati con due
 * conteggi e tenuti in cache un'ora: aggiornarli a ogni visita costerebbe
 * due scansioni di tabella per niente.
 */
final class HomeController extends Controller
{
    private const RECENT = 6;

    private const STATS_TTL = 3600;

    /** Secondi di cache nel browser per la pagina di ingresso. */
    private const BROWSER_CACHE = 60;

    public function index(): Response
    {
        $response = $this->view('platform/home', [
            'pageTitle'   => (string) Config::get('site.name', 'Noblogs')
                . ' — ' . __('platform.home.title'),
            'description' => __('platform.home.lead'),
            'bodyClass'   => 'landing',
            'canonical'   => Url::platform('/'),
            'indexable'   => true,
            'loggedIn'    => Auth::check(),
            'stats'       => $this->stats(),
            'entries'     => DiscoverController::showcase([
                'order' => 'recenti',
                'limit' => self::RECENT,
            ]),
            'domain'      => Url::mainDomain(),
            'old'         => Session::takeOldInput(),
            'tagline'     => (string) Config::get('site.tagline', ''),
        ]);

        // Solo cache privata del browser: la pagina può contenere un messaggio
        // flash (per esempio dopo l'uscita) e ogni risposta del dominio
        // principale porta un Set-Cookie di sessione, che in una cache
        // condivisa finirebbe per essere servito a tutti.
        return $response
            ->withHeader('Cache-Control', 'private, max-age=' . self::BROWSER_CACHE)
            ->withHeader('Vary', 'Cookie');
    }

    /**
     * Blog attivi e articoli pubblicati.
     *
     * @return array{blogs:int,posts:int}
     */
    private function stats(): array
    {
        $cached = Cache::get('platform/stats');
        if ($cached !== null) {
            $decoded = json_decode($cached, true);
            if (is_array($decoded) && isset($decoded['blogs'], $decoded['posts'])) {
                return ['blogs' => (int) $decoded['blogs'], 'posts' => (int) $decoded['posts']];
            }
        }

        $db = Database::instance();

        $stats = [
            // «Attivo» vuol dire: approvato, visibile e con un proprietario
            // attivo. Un blog nascosto o in attesa di revisione non conta.
            'blogs' => (int) $db->fetchColumn(
                'SELECT COUNT(*) FROM {{blogs}} b
                 INNER JOIN {{users}} u ON u.id = b.user_id
                 WHERE b.hidden = 0 AND b.reviewed = 1 AND u.is_active = 1'
            ),
            'posts' => (int) $db->fetchColumn(
                'SELECT COUNT(*) FROM {{posts}} p
                 INNER JOIN {{blogs}} b ON b.id = p.blog_id
                 INNER JOIN {{users}} u ON u.id = b.user_id
                 WHERE p.is_page = 0 AND p.is_published = 1 AND p.hidden = 0
                   AND p.published_at <= UTC_TIMESTAMP()
                   AND b.hidden = 0 AND u.is_active = 1'
            ),
        ];

        Cache::put('platform/stats', json_encode($stats) ?: '', self::STATS_TTL);

        return $stats;
    }
}
