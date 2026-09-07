<?php

declare(strict_types=1);

namespace Noblogs\Models;

use Noblogs\Core\Config;
use Noblogs\Core\Database;
use Noblogs\Support\Dates;

/**
 * Statistiche di lettura.
 *
 * L'identità del lettore è sha256(ip + giorno + salt): cambia ogni notte, non
 * è reversibile senza il salt e non consente di seguire una persona nel tempo.
 * Nella tabella non finiscono né IP, né user agent, né identificativi di
 * sessione. La chiave unica su (blog, post, hash, giorno) fa sì che ricaricare
 * la stessa pagina cento volte conti come una lettura sola.
 */
final class Hit extends Model
{
    protected static string $table = 'hits';

    public static function identify(string $ip, ?string $day = null): string
    {
        $day ??= gmdate('Y-m-d');
        return hash('sha256', $ip . '|' . $day . '|' . Config::get('security.analytics_salt', 'noblogs'));
    }

    public static function record(
        Blog $blog,
        ?Post $post,
        string $hashId,
        ?string $referrer,
        ?string $country,
        ?string $device,
        ?string $browser
    ): void {
        if (!$blog->analytics_active) {
            return;
        }

        // INSERT IGNORE: la seconda visita nello stesso giorno viene scartata
        // dal vincolo di unicità senza bisogno di una SELECT preventiva.
        Database::instance()->query(
            'INSERT IGNORE INTO {{hits}}
                (blog_id, post_id, hash_id, hit_date, created_at, referrer, country, device, browser)
             VALUES (:blog_id, :post_id, :hash_id, :hit_date, :created_at, :referrer, :country, :device, :browser)',
            [
                'blog_id'    => $blog->id,
                'post_id'    => $post?->id ?? 0,
                'hash_id'    => $hashId,
                'hit_date'   => gmdate('Y-m-d'),
                'created_at' => self::now(),
                'referrer'   => $referrer,
                'country'    => $country,
                'device'     => $device,
                'browser'    => $browser,
            ]
        );
    }

    /**
     * Normalizza il referrer a schema://host: il percorso completo direbbe
     * troppo su cosa stava leggendo la persona altrove.
     */
    public static function normalizeReferrer(string $referrer, Blog $blog): ?string
    {
        if (trim($referrer) === '') {
            return null;
        }
        $parts = parse_url($referrer);
        $host = $parts['host'] ?? null;
        if (!is_string($host) || $host === '') {
            return null;
        }
        $host = mb_strtolower($host);

        // Le visite interne al blog non sono un referrer utile.
        if ($host === $blog->domain || str_starts_with($host, $blog->subdomain . '.')) {
            return null;
        }

        $scheme = $parts['scheme'] ?? 'https';
        return mb_substr($scheme . '://' . $host, 0, 191);
    }

    // -----------------------------------------------------------------------
    // Aggregazioni
    // -----------------------------------------------------------------------

    /**
     * Serie giornaliera di letture e visitatori unici.
     *
     * @return list<array{date:string,reads:int,visitors:int}>
     */
    public static function daily(Blog $blog, int $days, ?int $postId = null): array
    {
        $params = ['blog_id' => $blog->id, 'from' => gmdate('Y-m-d', time() - ($days - 1) * 86400)];
        $filter = '';
        if ($postId !== null) {
            $filter = ' AND post_id = :post_id';
            $params['post_id'] = $postId;
        }

        $rows = Database::instance()->fetchAll(
            "SELECT hit_date, COUNT(*) AS reads, COUNT(DISTINCT hash_id) AS visitors
             FROM {{hits}}
             WHERE blog_id = :blog_id AND hit_date >= :from $filter
             GROUP BY hit_date ORDER BY hit_date ASC",
            $params
        );

        $byDate = [];
        foreach ($rows as $row) {
            $byDate[(string) $row['hit_date']] = [
                'reads'    => (int) $row['reads'],
                'visitors' => (int) $row['visitors'],
            ];
        }

        // Serie completa, con gli zeri: un grafico con i buchi mente.
        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = gmdate('Y-m-d', time() - $i * 86400);
            $series[] = [
                'date'     => $date,
                'reads'    => $byDate[$date]['reads'] ?? 0,
                'visitors' => $byDate[$date]['visitors'] ?? 0,
            ];
        }
        return $series;
    }

    /** @return array{reads:int,visitors:int} */
    public static function totals(Blog $blog, int $days, ?int $postId = null): array
    {
        $params = ['blog_id' => $blog->id, 'from' => gmdate('Y-m-d', time() - ($days - 1) * 86400)];
        $filter = '';
        if ($postId !== null) {
            $filter = ' AND post_id = :post_id';
            $params['post_id'] = $postId;
        }

        $row = Database::instance()->fetch(
            "SELECT COUNT(*) AS reads, COUNT(DISTINCT hash_id) AS visitors
             FROM {{hits}} WHERE blog_id = :blog_id AND hit_date >= :from $filter",
            $params
        );

        return [
            'reads'    => (int) ($row['reads'] ?? 0),
            'visitors' => (int) ($row['visitors'] ?? 0),
        ];
    }

    /**
     * Classifica dei contenuti più letti, homepage compresa.
     *
     * @return list<array{post_id:int,title:string,slug:string,reads:int,visitors:int,upvotes:int}>
     */
    public static function topPosts(Blog $blog, int $days, int $limit = 50): array
    {
        $rows = Database::instance()->fetchAll(
            'SELECT h.post_id, COUNT(*) AS reads, COUNT(DISTINCT h.hash_id) AS visitors,
                    p.title, p.slug, p.upvotes
             FROM {{hits}} h
             LEFT JOIN {{posts}} p ON p.id = h.post_id
             WHERE h.blog_id = :blog_id AND h.hit_date >= :from
             GROUP BY h.post_id, p.title, p.slug, p.upvotes
             ORDER BY reads DESC
             LIMIT ' . max(1, $limit),
            ['blog_id' => $blog->id, 'from' => gmdate('Y-m-d', time() - ($days - 1) * 86400)]
        );

        return array_map(static fn(array $row): array => [
            'post_id'  => (int) $row['post_id'],
            'title'    => (int) $row['post_id'] === 0 ? __('analytics.homepage') : (string) ($row['title'] ?? '—'),
            'slug'     => (string) ($row['slug'] ?? ''),
            'reads'    => (int) $row['reads'],
            'visitors' => (int) $row['visitors'],
            'upvotes'  => (int) ($row['upvotes'] ?? 0),
        ], $rows);
    }

    /**
     * Ripartizione per una dimensione (referrer, country, device, browser).
     *
     * @return list<array{label:string,reads:int}>
     */
    public static function breakdown(Blog $blog, string $dimension, int $days, int $limit = 20): array
    {
        if (!in_array($dimension, ['referrer', 'country', 'device', 'browser'], true)) {
            throw new \InvalidArgumentException("Dimensione non valida: $dimension");
        }

        $rows = Database::instance()->fetchAll(
            "SELECT `$dimension` AS label, COUNT(*) AS reads
             FROM {{hits}}
             WHERE blog_id = :blog_id AND hit_date >= :from AND `$dimension` IS NOT NULL AND `$dimension` <> ''
             GROUP BY `$dimension`
             ORDER BY reads DESC
             LIMIT " . max(1, $limit),
            ['blog_id' => $blog->id, 'from' => gmdate('Y-m-d', time() - ($days - 1) * 86400)]
        );

        return array_map(static fn(array $row): array => [
            'label' => (string) $row['label'],
            'reads' => (int) $row['reads'],
        ], $rows);
    }

    /** Lettori attivi negli ultimi minuti. */
    public static function currentReaders(Blog $blog, int $minutes = 5): int
    {
        return (int) Database::instance()->fetchColumn(
            'SELECT COUNT(DISTINCT hash_id) FROM {{hits}}
             WHERE blog_id = ? AND created_at >= UTC_TIMESTAMP() - INTERVAL ? MINUTE',
            [$blog->id, $minutes]
        );
    }

    /** Cancella le statistiche più vecchie del periodo di conservazione. */
    public static function prune(): int
    {
        $days = (int) Config::get('limits.analytics_retention', 365);
        if ($days <= 0) {
            return 0;
        }
        return Database::instance()->query(
            'DELETE FROM {{hits}} WHERE hit_date < ?',
            [gmdate('Y-m-d', time() - $days * 86400)]
        )->rowCount();
    }

    /**
     * Riconosce sommariamente sistema operativo e browser.
     *
     * Solo etichette generiche: nessuna versione, nessuna combinazione utile a
     * costruire un'impronta del dispositivo.
     */
    /** @return array{0:?string,1:?string} */
    public static function classifyUserAgent(string $userAgent): array
    {
        $ua = mb_strtolower($userAgent);
        if ($ua === '') {
            return [null, null];
        }

        $device = match (true) {
            str_contains($ua, 'android')                          => 'Android',
            str_contains($ua, 'iphone'), str_contains($ua, 'ipad') => 'iOS',
            str_contains($ua, 'windows')                          => 'Windows',
            str_contains($ua, 'mac os'), str_contains($ua, 'macintosh') => 'macOS',
            str_contains($ua, 'cros')                             => 'ChromeOS',
            str_contains($ua, 'linux'), str_contains($ua, 'bsd')  => 'Linux',
            default                                               => null,
        };

        $browser = match (true) {
            str_contains($ua, 'firefox'), str_contains($ua, 'fxios') => 'Firefox',
            str_contains($ua, 'edg/')                                => 'Edge',
            str_contains($ua, 'opr/'), str_contains($ua, 'opera')    => 'Opera',
            str_contains($ua, 'chrome'), str_contains($ua, 'crios')  => 'Chrome',
            str_contains($ua, 'safari')                              => 'Safari',
            default                                                  => null,
        };

        return [$device, $browser];
    }

    /**
     * Paese del lettore, se il reverse proxy lo dichiara.
     *
     * Noblogs non fa lookup GeoIP: su un hosting condiviso non c'è un database
     * MaxMind, e scaricarne uno a ogni richiesta non starebbe in piedi. Si
     * legge l'intestazione che Cloudflare, Fastly, CloudFront o App Engine
     * aggiungono già, e si accetta solo un codice ISO di due lettere.
     * Senza proxy, o con un codice non valido, si restituisce null e le
     * statistiche per paese restano vuote.
     */
    public static function countryFromRequest(\Noblogs\Core\Request $request): ?string
    {
        foreach ([
            'CF-IPCountry',
            'CloudFront-Viewer-Country',
            'Fastly-Country-Code',
            'Fastly-Geoip-Country-Code',
            'X-AppEngine-Country',
            'X-Country-Code',
        ] as $header) {
            $value = strtoupper(trim((string) $request->header($header)));
            if (preg_match('/^[A-Z]{2}$/', $value) === 1 && !in_array($value, ['XX', 'T1', 'A1', 'A2', 'O1'], true)) {
                return $value;
            }
        }

        return null;
    }
}
