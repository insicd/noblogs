<?php

declare(strict_types=1);

namespace Noblogs\Federation;

use Noblogs\Core\Database;
use Noblogs\Models\ApFollower;
use Noblogs\Models\Blog;
use Noblogs\Models\Post;

/**
 * Coda delle attività in uscita.
 *
 * Su hosting condiviso non si può tenere aperta la richiesta per tutti i
 * follower: si accoda, si prova un piccolo lotto subito, e il resto lo
 * smaltisce la manutenzione (e la pagina Notifiche).
 */
final class Delivery
{
    private const BATCH = 8;
    private const MAX_ATTEMPTS = 8;

    public static function publish(Blog $blog, Post $post, string $type): void
    {
        if (!ActivityPub::blogEnabled($blog) || $post->is_page) {
            return;
        }
        if ($type !== 'Delete' && !$post->isVisible()) {
            return;
        }

        Schema::ensure();
        $activity = ActivityPub::wrapActivity($blog, $post, $type);
        $when = $type === 'Create' && $post->isScheduled()
            ? $post->published_at
            : gmdate('Y-m-d H:i:s');

        self::enqueue($blog, $activity, $when);
        self::processDue(self::BATCH);
    }

    /** @param array<string,mixed> $activity */
    public static function enqueue(Blog $blog, array $activity, ?string $notBefore = null): void
    {
        self::enqueueTo($blog, $activity, ApFollower::inboxes($blog), $notBefore);
    }

    /**
     * Accoda la stessa attività verso un elenco esplicito di inbox.
     *
     * @param array<string,mixed> $activity
     * @param list<string> $inboxes
     */
    public static function enqueueTo(Blog $blog, array $activity, array $inboxes, ?string $notBefore = null): void
    {
        Schema::ensure();
        $payload = json_encode($activity, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($payload) || $payload === '') {
            return;
        }

        $when = $notBefore ?? gmdate('Y-m-d H:i:s');
        $now = gmdate('Y-m-d H:i:s');
        $db = Database::instance();
        $seen = [];

        foreach ($inboxes as $inbox) {
            if (!is_string($inbox) || $inbox === '' || isset($seen[$inbox]) || !Http::isSafeUrl($inbox)) {
                continue;
            }
            $seen[$inbox] = true;
            $db->insert('ap_deliveries', [
                'blog_id'         => $blog->id,
                'inbox_url'       => $inbox,
                'payload'         => $payload,
                'attempts'        => 0,
                'last_error'      => null,
                'next_attempt_at' => $when,
                'created_at'      => $now,
            ]);
        }
    }

    /**
     * POST immediato; se fallisce resta in coda per i retry.
     *
     * @param array<string,mixed> $activity
     * @param list<string> $inboxes
     */
    public static function sendNow(Blog $blog, array $activity, array $inboxes): bool
    {
        $targets = [];
        foreach ($inboxes as $inbox) {
            if (!is_string($inbox) || $inbox === '' || isset($targets[$inbox]) || !Http::isSafeUrl($inbox)) {
                continue;
            }
            $targets[$inbox] = true;
        }
        $urls = array_keys($targets);
        if ($urls === []) {
            return false;
        }

        foreach ($urls as $inbox) {
            $status = Http::postSigned($blog, $inbox, $activity);
            if ($status >= 200 && $status < 300) {
                return true;
            }
        }

        self::enqueueTo($blog, $activity, $urls);
        return false;
    }

    public static function processDue(int $limit = 20): int
    {
        Schema::ensure();
        $db = Database::instance();
        $rows = $db->fetchAll(
            'SELECT * FROM {{ap_deliveries}}
             WHERE next_attempt_at <= ?
             ORDER BY id ASC
             LIMIT ' . max(1, $limit),
            [gmdate('Y-m-d H:i:s')]
        );

        $sent = 0;
        foreach ($rows as $row) {
            $blog = Blog::find((int) $row['blog_id']);
            $payload = json_decode((string) $row['payload'], true);
            if ($blog === null || !is_array($payload) || !ActivityPub::blogEnabled($blog)) {
                $db->delete('ap_deliveries', 'id = ?', [(int) $row['id']]);
                continue;
            }

            $status = Http::postSigned($blog, (string) $row['inbox_url'], $payload);
            if ($status >= 200 && $status < 300) {
                $db->delete('ap_deliveries', 'id = ?', [(int) $row['id']]);
                $sent++;
                continue;
            }

            $attempts = (int) $row['attempts'] + 1;
            if ($attempts >= self::MAX_ATTEMPTS) {
                $db->delete('ap_deliveries', 'id = ?', [(int) $row['id']]);
                continue;
            }

            $delay = min(86400, 300 * (2 ** ($attempts - 1)));
            $db->update(
                'ap_deliveries',
                [
                    'attempts'        => $attempts,
                    'last_error'      => $status > 0 ? 'HTTP ' . $status : 'consegna non riuscita',
                    'next_attempt_at' => gmdate('Y-m-d H:i:s', time() + $delay),
                ],
                'id = :row_id',
                ['row_id' => (int) $row['id']]
            );
        }

        return $sent;
    }
}
