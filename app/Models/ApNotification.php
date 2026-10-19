<?php

declare(strict_types=1);

namespace Noblogs\Models;

use Noblogs\Federation\Schema;

/**
 * Avviso per l'autore: un follow, un commento, un apprezzamento dal fediverso.
 */
final class ApNotification extends Model
{
    protected static string $table = 'ap_notifications';

    protected static array $casts = [
        'id'        => 'int',
        'blog_id'   => 'int',
        'post_id'   => 'int',
        'parent_id' => 'int',
        'is_read'   => 'bool',
    ];

    public int $blog_id = 0;
    public ?int $post_id = null;
    public ?int $parent_id = null;
    public string $type = '';
    public string $actor_id = '';
    public ?string $handle = null;
    public ?string $display_name = null;
    public ?string $avatar_url = null;
    public ?string $content = null;
    public ?string $activity_id = null;
    public ?string $object_id = null;
    public bool $is_read = false;
    public ?string $deleted_at = null;
    public string $created_at = '';

    public static function unreadCount(int $blogId): int
    {
        Schema::ensure();
        return (int) self::db()->fetchColumn(
            'SELECT COUNT(*) FROM {{ap_notifications}} WHERE blog_id = ? AND is_read = 0',
            [$blogId]
        );
    }

    /** @return list<self> */
    public static function recent(Blog $blog, int $limit = 80): array
    {
        Schema::ensure();
        return self::hydrateAll(self::db()->fetchAll(
            'SELECT * FROM {{ap_notifications}} WHERE blog_id = ? AND type <> ?
             ORDER BY created_at DESC LIMIT ' . (int) $limit,
            [$blog->id, 'reply']
        ));
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function record(Blog $blog, array $data): void
    {
        Schema::ensure();
        $activityId = isset($data['activity_id']) ? (string) $data['activity_id'] : '';
        if ($activityId !== '') {
            $exists = self::db()->fetchColumn(
                'SELECT 1 FROM {{ap_notifications}} WHERE blog_id = ? AND activity_id = ?',
                [$blog->id, $activityId]
            );
            if ($exists !== null) {
                return;
            }
        }

        try {
            self::db()->insert('ap_notifications', [
                'blog_id'      => $blog->id,
                'post_id'      => $data['post_id'] ?? null,
                'parent_id'    => $data['parent_id'] ?? null,
                'type'         => (string) ($data['type'] ?? 'notice'),
                'actor_id'     => (string) ($data['actor_id'] ?? ''),
                'handle'       => $data['handle'] ?? null,
                'display_name' => $data['display_name'] ?? null,
                'avatar_url'   => $data['avatar_url'] ?? null,
                'content'      => $data['content'] ?? null,
                'activity_id'  => $activityId !== '' ? $activityId : null,
                'object_id'    => $data['object_id'] ?? null,
                'is_read'      => !empty($data['is_read']) ? 1 : 0,
                'deleted_at'   => null,
                'created_at'   => self::now(),
            ]);
        } catch (\Throwable) {
            // Duplicato sulla chiave unica: un retry del remoto, si ignora.
        }
    }

    /** @return list<self> */
    public static function comments(Blog $blog, int $limit = 80): array
    {
        Schema::ensure();
        return self::hydrateAll(self::db()->fetchAll(
            'SELECT * FROM {{ap_notifications}}
             WHERE blog_id = ? AND type = ?
             ORDER BY created_at DESC LIMIT ' . (int) $limit,
            [$blog->id, 'comment']
        ));
    }

    /**
     * Risposte dell'autore, raggruppate per commento di origine.
     *
     * @return array<int,list<self>>
     */
    public static function repliesGrouped(Blog $blog): array
    {
        Schema::ensure();
        $rows = self::hydrateAll(self::db()->fetchAll(
            'SELECT * FROM {{ap_notifications}}
             WHERE blog_id = ? AND type = ? AND parent_id IS NOT NULL
             ORDER BY created_at ASC',
            [$blog->id, 'reply']
        ));
        $grouped = [];
        foreach ($rows as $row) {
            if ($row->parent_id === null) {
                continue;
            }
            $grouped[$row->parent_id][] = $row;
        }
        return $grouped;
    }

    public static function findByObject(Blog $blog, string $objectId): ?self
    {
        Schema::ensure();
        if ($objectId === '') {
            return null;
        }
        return self::hydrateOrNull(self::db()->fetch(
            'SELECT * FROM {{ap_notifications}} WHERE blog_id = ? AND object_id = ?',
            [$blog->id, $objectId]
        ));
    }

    public static function markAllRead(Blog $blog): void
    {
        Schema::ensure();
        self::db()->query(
            'UPDATE {{ap_notifications}} SET is_read = 1 WHERE blog_id = ? AND is_read = 0',
            [$blog->id]
        );
    }

    public function markRead(): void
    {
        if ($this->is_read) {
            return;
        }
        $this->is_read = true;
        $this->updateRow(['is_read' => 1]);
    }

    public function isDeleted(): bool
    {
        return $this->deleted_at !== null && $this->deleted_at !== '';
    }

    public function markDeleted(): void
    {
        if ($this->isDeleted()) {
            return;
        }
        $this->content = null;
        $this->is_read = true;
        $this->deleted_at = self::now();
        $this->updateRow([
            'content'    => null,
            'is_read'    => 1,
            'deleted_at' => $this->deleted_at,
        ]);
    }

    /**
     * Un Delete remoto della Note: i commenti restano visibili come
     * eliminati, like e boost si tolgono.
     *
     * @param list<string> $objectIds
     */
    public static function tombstoneByObject(Blog $blog, string $actorId, array $objectIds): void
    {
        Schema::ensure();
        foreach ($objectIds as $objectId) {
            if ($objectId === '') {
                continue;
            }
            foreach (self::hydrateAll(self::db()->fetchAll(
                'SELECT * FROM {{ap_notifications}}
                 WHERE blog_id = ? AND actor_id = ? AND object_id = ?',
                [$blog->id, $actorId, $objectId]
            )) as $row) {
                if (in_array($row->type, ['comment', 'reply'], true)) {
                    $row->markDeleted();
                } else {
                    $row->delete();
                }
            }
        }
    }

    public static function tombstoneByActor(Blog $blog, string $actorId): void
    {
        Schema::ensure();
        foreach (self::hydrateAll(self::db()->fetchAll(
            'SELECT * FROM {{ap_notifications}} WHERE blog_id = ? AND actor_id = ?',
            [$blog->id, $actorId]
        )) as $row) {
            if (in_array($row->type, ['comment', 'reply'], true)) {
                $row->markDeleted();
            } else {
                $row->delete();
            }
        }
    }

    public function post(): ?Post
    {
        return $this->post_id !== null && $this->post_id > 0 ? Post::find($this->post_id) : null;
    }

    public function actorLabel(): string
    {
        if ($this->display_name !== null && $this->display_name !== '') {
            return $this->display_name;
        }
        if ($this->handle !== null && $this->handle !== '') {
            return '@' . ltrim($this->handle, '@');
        }
        return $this->actor_id;
    }
}
