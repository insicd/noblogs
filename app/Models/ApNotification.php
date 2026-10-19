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
        'id'      => 'int',
        'blog_id' => 'int',
        'post_id' => 'int',
        'is_read' => 'bool',
    ];

    public int $blog_id = 0;
    public ?int $post_id = null;
    public string $type = '';
    public string $actor_id = '';
    public ?string $handle = null;
    public ?string $display_name = null;
    public ?string $avatar_url = null;
    public ?string $content = null;
    public ?string $activity_id = null;
    public ?string $object_id = null;
    public bool $is_read = false;
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
            'SELECT * FROM {{ap_notifications}} WHERE blog_id = ?
             ORDER BY created_at DESC LIMIT ' . (int) $limit,
            [$blog->id]
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
                'type'         => (string) ($data['type'] ?? 'notice'),
                'actor_id'     => (string) ($data['actor_id'] ?? ''),
                'handle'       => $data['handle'] ?? null,
                'display_name' => $data['display_name'] ?? null,
                'avatar_url'   => $data['avatar_url'] ?? null,
                'content'      => $data['content'] ?? null,
                'activity_id'  => $activityId !== '' ? $activityId : null,
                'object_id'    => $data['object_id'] ?? null,
                'is_read'      => 0,
                'created_at'   => self::now(),
            ]);
        } catch (\Throwable) {
            // Duplicato sulla chiave unica: un retry del remoto, si ignora.
        }
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
