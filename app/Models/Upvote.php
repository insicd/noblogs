<?php

declare(strict_types=1);

namespace Noblogs\Models;

use Noblogs\Core\Config;
use Noblogs\Core\Database;

/**
 * Apprezzamenti ai post.
 *
 * L'identità è sha256(ip + anno + salt): un voto per post all'anno, senza
 * conoscere chi vota. I voti sospetti non vengono rifiutati (chi automatizza
 * capirebbe subito cosa lo tradisce) ma marcati ed esclusi dal conteggio.
 */
final class Upvote extends Model
{
    protected static string $table = 'upvotes';

    public static function identify(string $ip): string
    {
        return hash('sha256', $ip . '|' . gmdate('Y') . '|upvote|' . Config::get('security.analytics_salt', 'noblogs'));
    }

    public static function exists(int $postId, string $hashId): bool
    {
        return Database::instance()->fetchColumn(
            'SELECT 1 FROM {{upvotes}} WHERE post_id = ? AND hash_id = ?',
            [$postId, $hashId]
        ) !== null;
    }

    /** Voto presente e conteggiato (non marcato come sospetto). */
    public static function isValid(int $postId, string $hashId): bool
    {
        return self::exists($postId, $hashId) && !self::isMarked($postId, $hashId);
    }

    /**
     * Mette un voto conteggiato. Se la riga c'è già (anche marcata per errore)
     * la promuove a voto valido.
     */
    public static function give(Post $post, string $hashId, bool $suspicious = false): void
    {
        Database::instance()->query(
            'INSERT INTO {{upvotes}} (post_id, hash_id, marked, signals, created_at)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                marked = VALUES(marked),
                signals = VALUES(signals)',
            [
                $post->id,
                $hashId,
                $suspicious ? 1 : 0,
                $suspicious ? 'honeypot' : null,
                self::now(),
            ]
        );
    }

    /**
     * @param list<string> $signals
     */
    public static function cast(Post $post, string $hashId, array $signals = []): bool
    {
        $inserted = Database::instance()->query(
            'INSERT IGNORE INTO {{upvotes}} (post_id, hash_id, marked, signals, created_at)
             VALUES (?, ?, ?, ?, ?)',
            [
                $post->id,
                $hashId,
                $signals === [] ? 0 : 1,
                $signals === [] ? null : mb_substr(implode(',', $signals), 0, 255),
                self::now(),
            ]
        )->rowCount() > 0;

        if ($inserted && $signals === []) {
            Database::instance()->query(
                'UPDATE {{posts}} SET upvotes = upvotes + 1 WHERE id = ?',
                [$post->id]
            );
            $post->upvotes++;
            Database::instance()->query(
                'UPDATE {{posts}} SET score = ? WHERE id = ?',
                [$post->computeScore(), $post->id]
            );
        }

        return $inserted;
    }

    public static function isMarked(int $postId, string $hashId): bool
    {
        return (int) Database::instance()->fetchColumn(
            'SELECT marked FROM {{upvotes}} WHERE post_id = ? AND hash_id = ?',
            [$postId, $hashId]
        ) === 1;
    }

    /** Promuove un voto marcato per errore a voto valido. */
    public static function confirm(Post $post, string $hashId): bool
    {
        $updated = Database::instance()->query(
            'UPDATE {{upvotes}} SET marked = 0, signals = NULL
             WHERE post_id = ? AND hash_id = ? AND marked = 1',
            [$post->id, $hashId]
        )->rowCount() > 0;

        if ($updated) {
            $post->recalculateUpvotes();
        }

        return $updated;
    }

    public static function remove(Post $post, string $hashId): bool
    {
        $deleted = Database::instance()->delete(
            'upvotes',
            'post_id = ? AND hash_id = ?',
            [$post->id, $hashId]
        ) > 0;

        if ($deleted) {
            $post->recalculateUpvotes();
        }
        return $deleted;
    }
}
