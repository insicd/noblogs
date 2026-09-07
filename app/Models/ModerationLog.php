<?php

declare(strict_types=1);

namespace Noblogs\Models;

use Noblogs\Core\Database;
use Noblogs\Support\Dates;

/**
 * Traccia delle decisioni di moderazione. Serve a rendere verificabile chi ha
 * approvato, nascosto o ripristinato un blog e perché.
 */
final class ModerationLog
{
    public static function record(?int $blogId, ?int $actorId, string $action, string $note = ''): void
    {
        Database::instance()->insert('moderation_log', [
            'blog_id'    => $blogId,
            'actor_id'   => $actorId,
            'action'     => $action,
            'note'       => $note !== '' ? $note : null,
            'created_at' => Dates::nowString(),
        ]);
    }

    /** @return list<array<string,mixed>> */
    public static function recent(int $limit = 100, ?int $blogId = null): array
    {
        $sql = 'SELECT m.*, u.email AS actor_email, b.subdomain
                FROM {{moderation_log}} m
                LEFT JOIN {{users}} u ON u.id = m.actor_id
                LEFT JOIN {{blogs}} b ON b.id = m.blog_id';
        $params = [];
        if ($blogId !== null) {
            $sql .= ' WHERE m.blog_id = ?';
            $params[] = $blogId;
        }
        $sql .= ' ORDER BY m.created_at DESC LIMIT ' . max(1, $limit);

        return Database::instance()->fetchAll($sql, $params);
    }
}
