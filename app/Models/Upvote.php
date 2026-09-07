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

    /**
     * Registra un voto. Restituisce false se era già presente.
     *
     * @param list<string> $signals Indizi di automazione raccolti dal controller.
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
