<?php

declare(strict_types=1);

namespace Noblogs\Models;

use Noblogs\Federation\Schema;

/**
 * Un follower remoto di un blog, arrivato con un Follow ActivityPub.
 */
final class ApFollower extends Model
{
    protected static string $table = 'ap_followers';

    protected static array $casts = [
        'id'      => 'int',
        'blog_id' => 'int',
    ];

    public int $blog_id = 0;
    public string $actor_id = '';
    public string $inbox_url = '';
    public ?string $shared_inbox_url = null;
    public ?string $handle = null;
    public ?string $display_name = null;
    public ?string $avatar_url = null;
    public string $created_at = '';

    public static function count(Blog $blog): int
    {
        Schema::ensure();
        return (int) self::db()->fetchColumn(
            'SELECT COUNT(*) FROM {{ap_followers}} WHERE blog_id = ?',
            [$blog->id]
        );
    }

    public static function findByActor(Blog $blog, string $actorId): ?self
    {
        Schema::ensure();
        return self::hydrateOrNull(self::db()->fetch(
            'SELECT * FROM {{ap_followers}} WHERE blog_id = ? AND actor_id = ?',
            [$blog->id, $actorId]
        ));
    }

    /** @return list<self> */
    public static function page(Blog $blog, int $page, int $perPage = 20): array
    {
        Schema::ensure();
        $offset = max(0, ($page - 1) * $perPage);
        return self::hydrateAll(self::db()->fetchAll(
            'SELECT * FROM {{ap_followers}} WHERE blog_id = ?
             ORDER BY created_at DESC
             LIMIT ' . (int) $perPage . ' OFFSET ' . $offset,
            [$blog->id]
        ));
    }

    /** @return list<self> */
    public static function allFor(Blog $blog): array
    {
        Schema::ensure();
        return self::hydrateAll(self::db()->fetchAll(
            'SELECT * FROM {{ap_followers}} WHERE blog_id = ? ORDER BY created_at DESC',
            [$blog->id]
        ));
    }

    /**
     * @param array<string,mixed> $actor
     */
    public static function upsert(Blog $blog, array $actor): self
    {
        Schema::ensure();
        $existing = self::findByActor($blog, (string) $actor['actor_id']);
        $data = [
            'inbox_url'        => (string) $actor['inbox_url'],
            'shared_inbox_url' => $actor['shared_inbox_url'] ?? null,
            'handle'           => $actor['handle'] ?? null,
            'display_name'     => $actor['display_name'] ?? null,
            'avatar_url'       => $actor['avatar_url'] ?? null,
        ];

        if ($existing !== null) {
            $existing->updateRow($data);
            foreach ($data as $key => $value) {
                $existing->$key = $value;
            }
            return $existing;
        }

        $row = new self();
        $row->blog_id = $blog->id;
        $row->actor_id = (string) $actor['actor_id'];
        $row->inbox_url = $data['inbox_url'];
        $row->shared_inbox_url = $data['shared_inbox_url'];
        $row->handle = $data['handle'];
        $row->display_name = $data['display_name'];
        $row->avatar_url = $data['avatar_url'];
        $row->created_at = self::now();
        $row->insertRow([
            'blog_id'          => $row->blog_id,
            'actor_id'         => $row->actor_id,
            'inbox_url'        => $row->inbox_url,
            'shared_inbox_url' => $row->shared_inbox_url,
            'handle'           => $row->handle,
            'display_name'     => $row->display_name,
            'avatar_url'       => $row->avatar_url,
            'created_at'       => $row->created_at,
        ]);

        return $row;
    }

    public static function remove(Blog $blog, string $actorId): bool
    {
        Schema::ensure();
        return self::db()->delete(
            'ap_followers',
            'blog_id = ? AND actor_id = ?',
            [$blog->id, $actorId]
        ) > 0;
    }

    /** Inbox distinte (preferisce la shared inbox quando c'è). @return list<string> */
    public static function inboxes(Blog $blog): array
    {
        $seen = [];
        foreach (self::allFor($blog) as $follower) {
            $url = $follower->shared_inbox_url !== null && $follower->shared_inbox_url !== ''
                ? $follower->shared_inbox_url
                : $follower->inbox_url;
            if ($url !== '') {
                $seen[$url] = true;
            }
        }
        return array_keys($seen);
    }
}
