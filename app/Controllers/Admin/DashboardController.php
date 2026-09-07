<?php

declare(strict_types=1);

namespace Noblogs\Controllers\Admin;

use Noblogs\Core\Database;
use Noblogs\Core\Response;
use Noblogs\Core\Url;
use Noblogs\Markdown\Cache;
use Noblogs\Models\Blog;
use Noblogs\Models\Hit;
use Noblogs\Models\ModerationLog;
use Noblogs\Models\Subscriber;
use Noblogs\Models\Theme;
use Noblogs\Support\Str;

/**
 * Quadro generale, registro di moderazione e manutenzione.
 */
final class DashboardController extends AdminController
{
    /** Blog mostrati nella coda: oltre non è più una coda, è un elenco. */
    private const QUEUE_LIMIT = 40;

    public function index(): Response
    {
        if ($denied = $this->guard()) {
            return $denied;
        }

        return $this->panel('admin/dashboard', [
            'activeNav' => 'dashboard',
            'stats'     => $this->stats(),
            'queue'     => $this->queue(),
            'recentLog' => ModerationLog::recent(8),
        ]);
    }

    public function log(): Response
    {
        if ($denied = $this->guard()) {
            return $denied;
        }

        return $this->panel('admin/log', [
            'activeNav' => 'log',
            'entries'   => ModerationLog::recent(300),
        ]);
    }

    /**
     * Manutenzione su richiesta.
     *
     * Le stesse operazioni le esegue `bin/noblogs manutenzione` da cron: qui
     * servono a chi non ha accesso alla riga di comando, cioè quasi chiunque
     * stia su un hosting condiviso.
     */
    public function maintenance(): Response
    {
        if ($denied = $this->guard()) {
            return $denied;
        }
        if ($response = $this->requireCsrf()) {
            return $response;
        }

        $report = self::runMaintenance();

        $this->flash('success', __('admin.maintenance.done', [
            'hits'        => $report['hits'],
            'subscribers' => $report['subscribers'],
            'cache'       => $report['cache'],
            'themes'      => $report['themes'],
            'blogs'       => $report['blogs'],
        ]));
        $this->record(null, 'manutenzione', sprintf(
            'statistiche: %d, iscrizioni: %d, cache: %d, temi: %d, blog: %d',
            $report['hits'],
            $report['subscribers'],
            $report['cache'],
            $report['themes'],
            $report['blogs']
        ));

        return $this->redirect(Url::to('/admin'));
    }

    /**
     * Operazioni periodiche, condivise con la riga di comando.
     *
     * @return array{hits:int,subscribers:int,cache:int,themes:int,blogs:int}
     */
    public static function runMaintenance(): array
    {
        return [
            'hits'        => Hit::prune(),
            'subscribers' => Subscriber::pruneUnconfirmed(),
            'cache'       => Cache::prune(),
            'themes'      => Theme::sync(),
            'blogs'       => self::refreshBlogCounters(),
        ];
    }

    /**
     * Riallinea i contatori denormalizzati dei blog: data dell'ultimo
     * articolo, articoli delle ultime 12 ore e spazio occupato.
     *
     * @return int Blog aggiornati.
     */
    public static function refreshBlogCounters(): int
    {
        $db = Database::instance();
        $count = 0;

        foreach ($db->fetchAll('SELECT * FROM {{blogs}} ORDER BY id ASC') as $row) {
            $blog = Blog::hydrate($row);
            $blog->refreshCounters();

            $used = (int) $db->fetchColumn(
                'SELECT COALESCE(SUM(size), 0) FROM {{media}} WHERE blog_id = ?',
                [$blog->id]
            );
            if ($used !== $blog->storage_used) {
                $db->update('blogs', ['storage_used' => $used], 'id = :blog_id', ['blog_id' => $blog->id]);
            }

            $count++;
        }

        return $count;
    }

    // -----------------------------------------------------------------------
    // Dati della pagina
    // -----------------------------------------------------------------------

    /** @return array<string,int> */
    private function stats(): array
    {
        $db = Database::instance();

        return [
            'blogs'    => (int) $db->fetchColumn('SELECT COUNT(*) FROM {{blogs}}'),
            'pending'  => self::pendingCount(),
            'hidden'   => (int) $db->fetchColumn('SELECT COUNT(*) FROM {{blogs}} WHERE hidden = 1'),
            'flagged'  => (int) $db->fetchColumn('SELECT COUNT(*) FROM {{blogs}} WHERE flagged = 1'),
            'users'    => (int) $db->fetchColumn('SELECT COUNT(*) FROM {{users}}'),
            'staff'    => (int) $db->fetchColumn("SELECT COUNT(*) FROM {{users}} WHERE role IN ('admin','moderator')"),
            'posts'    => (int) $db->fetchColumn('SELECT COUNT(*) FROM {{posts}} WHERE is_page = 0'),
            'reads'    => (int) $db->fetchColumn(
                'SELECT COUNT(*) FROM {{hits}} WHERE hit_date >= ?',
                [gmdate('Y-m-d', time() - 29 * 86400)]
            ),
            'storage'  => (int) $db->fetchColumn('SELECT COALESCE(SUM(storage_used), 0) FROM {{blogs}}'),
        ];
    }

    /**
     * Coda di moderazione: i blog che aspettano una decisione, i più sospetti
     * per primi, con abbastanza contesto da decidere senza aprirli.
     *
     * @return list<array{blog:Blog,owner_email:string,owner_created:string,posts:int,excerpt:string,titles:list<string>}>
     */
    private function queue(): array
    {
        $db = Database::instance();

        $rows = $db->fetchAll(
            'SELECT b.*, u.email AS owner_email, u.created_at AS owner_created,
                    (SELECT COUNT(*) FROM {{posts}} p WHERE p.blog_id = b.id) AS post_count
             FROM {{blogs}} b
             INNER JOIN {{users}} u ON u.id = b.user_id
             WHERE b.to_review = 1 OR b.reviewed = 0
             ORDER BY b.dodginess_score DESC, b.created_at ASC
             LIMIT ' . self::QUEUE_LIMIT
        );

        if ($rows === []) {
            return [];
        }

        $titles = $this->recentTitles(array_map(static fn(array $r): int => (int) $r['id'], $rows));

        $queue = [];
        foreach ($rows as $row) {
            $blog = Blog::hydrate($row);
            $queue[] = [
                'blog'          => $blog,
                'owner_email'   => (string) $row['owner_email'],
                'owner_created' => (string) $row['owner_created'],
                'posts'         => (int) $row['post_count'],
                'excerpt'       => Str::limit(Str::plain($blog->content ?? ''), 320),
                'titles'        => $titles[$blog->id] ?? [],
            ];
        }

        return $queue;
    }

    /**
     * Titoli degli articoli più recenti dei blog in coda: nello spam i titoli
     * dicono quasi sempre tutto quello che serve sapere.
     *
     * @param list<int> $blogIds
     * @return array<int,list<string>>
     */
    private function recentTitles(array $blogIds): array
    {
        if ($blogIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($blogIds), '?'));
        $rows = Database::instance()->fetchAll(
            "SELECT blog_id, title FROM {{posts}}
             WHERE blog_id IN ($placeholders)
             ORDER BY blog_id ASC, published_at DESC",
            $blogIds
        );

        $titles = [];
        foreach ($rows as $row) {
            $blogId = (int) $row['blog_id'];
            if (count($titles[$blogId] ?? []) >= 4) {
                continue;
            }
            $titles[$blogId][] = Str::limit((string) $row['title'], 70);
        }

        return $titles;
    }
}
