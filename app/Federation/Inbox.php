<?php

declare(strict_types=1);

namespace Noblogs\Federation;

use Noblogs\Core\Database;
use Noblogs\Core\RateLimiter;
use Noblogs\Core\Request;
use Noblogs\Markdown\Sanitizer;
use Noblogs\Models\ApFollower;
use Noblogs\Models\ApNotification;
use Noblogs\Models\Blog;
use Noblogs\Models\Post;
use Noblogs\Support\Str;

/**
 * Attività in arrivo: Follow, Undo, commenti (Create Note), Like, Announce.
 */
final class Inbox
{
    public static function handle(Request $request, ?Blog $blog): int
    {
        Schema::ensure();

        if (RateLimiter::tooManyAttempts('ap-inbox:' . $request->ip(), 60, 300)) {
            return 429;
        }

        if (strlen($request->rawBody) > 1048576) {
            return 413;
        }

        $payload = json_decode($request->rawBody, true);
        if (!is_array($payload)) {
            return 400;
        }

        $type = self::typeOf($payload['type'] ?? null);
        $actorId = self::idOf($payload['actor'] ?? null);
        if ($actorId === '' || $type === '') {
            return 400;
        }

        $remote = self::loadRemote($actorId, $request);
        if ($remote === null) {
            return 401;
        }

        if (!Http::verify($request, (string) ($remote['public_key'] ?? ''))) {
            return 401;
        }

        if ($blog === null) {
            $blog = self::blogFromPayload($payload);
        }
        if ($blog === null && in_array($type, ['Create', 'Update'], true)) {
            $fetched = self::embeddedObject($payload['object'] ?? null);
            if (is_array($fetched)) {
                $blog = self::blogFromPayload(['object' => $fetched] + $payload);
            }
        }
        if ($blog === null && $type === 'Delete') {
            $blog = self::blogFromStoredObject($payload);
        }
        if ($blog === null) {
            return 404;
        }
        if ($type !== 'Delete' && !ActivityPub::blogEnabled($blog)) {
            return 404;
        }

        return match ($type) {
            'Follow' => self::follow($blog, $payload, $remote),
            'Undo' => self::undo($blog, $payload, $remote),
            'Create' => self::create($blog, $payload, $remote),
            'Like' => self::like($blog, $payload, $remote, 'like'),
            'Announce' => self::like($blog, $payload, $remote, 'announce'),
            'Delete' => self::delete($blog, $payload, $remote),
            'Update' => self::create($blog, $payload, $remote),
            default => 202,
        };
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $remote
     */
    private static function follow(Blog $blog, array $payload, array $remote): int
    {
        $actorUrl = ActivityPub::actorId($blog);
        $object = self::idOf($payload['object'] ?? null);
        if ($object !== '' && rtrim($object, '/') !== rtrim($actorUrl, '/')) {
            return 404;
        }

        ApFollower::upsert($blog, $remote);
        $followId = self::idOf($payload['id'] ?? null);
        ApNotification::record($blog, [
            'type'         => 'follow',
            'actor_id'     => $remote['actor_id'],
            'handle'       => $remote['handle'],
            'display_name' => $remote['display_name'],
            'avatar_url'   => $remote['avatar_url'],
            'activity_id'  => $followId !== '' ? $followId : null,
        ]);

        $followActor = self::idOf($payload['actor'] ?? $remote['actor_id']);
        if ($followActor === '') {
            $followActor = (string) $remote['actor_id'];
        }

        // Mastodon tiene il Follow in pending finché non arriva un Accept.
        // L'object deve essere il Follow originale (id e type), non una
        // stringa JSON e senza @context nidificato: Mastodon lo associa
        // al FollowRequest sia per uri sia come Follow incorporato.
        $follow = [
            'id' => $followId !== '' ? $followId : ($actorUrl . '/follow/' . Str::uid(12)),
            'type' => 'Follow',
            'actor' => $followActor,
            'object' => ($object !== '' ? $object : $actorUrl),
        ];
        $accept = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $actorUrl . '/accept/' . Str::uid(12),
            'type' => 'Accept',
            'actor' => $actorUrl,
            'object' => $follow,
            'to' => [$followActor],
        ];

        $inboxes = [];
        foreach (['inbox_url', 'shared_inbox_url'] as $key) {
            $url = trim((string) ($remote[$key] ?? ''));
            if ($url !== '') {
                $inboxes[] = $url;
            }
        }
        if ($inboxes !== []) {
            Delivery::sendNow($blog, $accept, $inboxes);
        }

        return 202;
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $remote
     */
    private static function undo(Blog $blog, array $payload, array $remote): int
    {
        $inner = $payload['object'] ?? null;
        $innerType = is_array($inner) ? self::typeOf($inner['type'] ?? null) : '';
        $actorId = (string) $remote['actor_id'];

        if ($innerType === 'Follow' || (!is_array($inner) && $inner === ActivityPub::actorId($blog))) {
            ApFollower::remove($blog, $actorId);
            ApNotification::record($blog, [
                'type'         => 'unfollow',
                'actor_id'     => $actorId,
                'handle'       => $remote['handle'],
                'display_name' => $remote['display_name'],
                'avatar_url'   => $remote['avatar_url'],
                'activity_id'  => self::idOf($payload['id'] ?? null) ?: null,
            ]);
            return 202;
        }

        if (in_array($innerType, ['Like', 'Announce'], true) && is_array($inner)) {
            $post = ActivityPub::postFromObjectId(self::idOf($inner['object'] ?? null));
            if ($post !== null && $post->blog_id === $blog->id) {
                Database::instance()->query(
                    'DELETE FROM {{ap_notifications}}
                     WHERE blog_id = ? AND actor_id = ? AND post_id = ? AND type = ?',
                    [
                        $blog->id,
                        $actorId,
                        $post->id,
                        $innerType === 'Announce' ? 'announce' : 'like',
                    ]
                );
            }
        }

        return 202;
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $remote
     */
    private static function create(Blog $blog, array $payload, array $remote): int
    {
        $object = self::embeddedObject($payload['object'] ?? null);
        if ($object === null) {
            return 202;
        }
        $objectType = self::typeOf($object['type'] ?? null);
        if (!in_array($objectType, ['Note', 'Article', 'Comment'], true)) {
            return 202;
        }

        $post = self::postFromReply($object) ?? self::postFromReply($payload);
        if ($post === null || $post->blog_id !== $blog->id) {
            return 202;
        }

        $html = self::objectContent($object);
        $html = (new Sanitizer())->clean($html);
        $text = Str::plain(strip_tags($html));

        ApNotification::record($blog, [
            'type'         => 'comment',
            'post_id'      => $post->id,
            'actor_id'     => $remote['actor_id'],
            'handle'       => $remote['handle'],
            'display_name' => $remote['display_name'],
            'avatar_url'   => $remote['avatar_url'],
            'content'      => Str::limit($text !== '' ? $text : strip_tags($html), 2000, ''),
            'activity_id'  => self::idOf($payload['id'] ?? $object['id'] ?? null) ?: null,
            'object_id'    => self::idOf($object['id'] ?? null) ?: null,
        ]);

        return 202;
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $remote
     */
    private static function like(Blog $blog, array $payload, array $remote, string $type): int
    {
        $post = ActivityPub::postFromObjectId(self::idOf($payload['object'] ?? null));
        if ($post === null || $post->blog_id !== $blog->id) {
            return 202;
        }

        ApNotification::record($blog, [
            'type'         => $type,
            'post_id'      => $post->id,
            'actor_id'     => $remote['actor_id'],
            'handle'       => $remote['handle'],
            'display_name' => $remote['display_name'],
            'avatar_url'   => $remote['avatar_url'],
            'activity_id'  => self::idOf($payload['id'] ?? null) ?: null,
            'object_id'    => ActivityPub::objectId($blog, $post),
        ]);

        return 202;
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<string,mixed> $remote
     */
    private static function delete(Blog $blog, array $payload, array $remote): int
    {
        $actorId = (string) ($remote['actor_id'] ?? '');
        $object = $payload['object'] ?? null;
        $objectId = self::idOf($object);
        if ($actorId === '' || $objectId === '') {
            return 202;
        }

        $objectType = is_array($object) ? self::typeOf($object['type'] ?? null) : '';
        $formerType = is_array($object) ? self::typeOf($object['formerType'] ?? null) : '';
        $isActor = $objectId === $actorId
            || in_array($objectType, ['Person', 'Service', 'Application', 'Group'], true)
            || ($objectType === 'Tombstone' && in_array($formerType, ['Person', 'Service', 'Application', 'Group'], true));

        if ($isActor) {
            ApNotification::tombstoneByActor($blog, $actorId);
            return 202;
        }

        $ids = self::idsOf($object);
        if (is_array($object)) {
            foreach (self::idsOf($object['atomUri'] ?? null) as $id) {
                $ids[] = $id;
            }
        }
        ApNotification::tombstoneByObject($blog, $actorId, array_values(array_unique($ids)));
        return 202;
    }

    /**
     * Un Delete di commento ha come object la Note remota: sulla shared inbox
     * il blog si ricava dalla notifica già salvata, non dall'URL dell'oggetto.
     *
     * @param array<string,mixed> $payload
     */
    private static function blogFromStoredObject(array $payload): ?Blog
    {
        foreach (self::idsOf($payload['object'] ?? null) as $id) {
            $row = Database::instance()->fetch(
                'SELECT blog_id FROM {{ap_notifications}} WHERE object_id = ? LIMIT 1',
                [$id]
            );
            if ($row !== null) {
                return Blog::find((int) $row['blog_id']);
            }
        }
        return null;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private static function blogFromPayload(array $payload): ?Blog
    {
        foreach (self::addressedUrls($payload) as $url) {
            $blog = ActivityPub::blogFromUrl($url);
            if ($blog !== null) {
                return $blog;
            }
            $post = ActivityPub::postFromObjectId($url);
            if ($post !== null) {
                return Blog::find($post->blog_id);
            }
        }
        return null;
    }

    /**
     * URL della nostra parte: actor, post, inReplyTo, menzioni, destinatari.
     * Serve alla shared inbox: un Create di commento ha come object la Note
     * remota, e il blog sta in inReplyTo, non nell'id dell'oggetto.
     *
     * @param array<string,mixed> $payload
     * @return list<string>
     */
    private static function addressedUrls(array $payload): array
    {
        $urls = [];
        $push = static function (mixed $value) use (&$urls): void {
            foreach (self::idsOf($value) as $id) {
                $urls[] = $id;
            }
            if (!is_array($value)) {
                return;
            }
            if (array_is_list($value) && !isset($value['id']) && !isset($value['@id'])) {
                foreach ($value as $item) {
                    if (is_array($item)) {
                        foreach (['inReplyTo', 'inReplyToAtomUri', 'href', 'url'] as $key) {
                            foreach (self::idsOf($item[$key] ?? null) as $inner) {
                                $urls[] = $inner;
                            }
                        }
                    }
                }
                return;
            }
            foreach (['inReplyTo', 'inReplyToAtomUri', 'href', 'url'] as $key) {
                foreach (self::idsOf($value[$key] ?? null) as $inner) {
                    $urls[] = $inner;
                }
            }
            if (isset($value['tag']) && is_array($value['tag'])) {
                foreach ($value['tag'] as $tag) {
                    foreach (self::idsOf(is_array($tag) ? ($tag['href'] ?? $tag) : $tag) as $href) {
                        $urls[] = $href;
                    }
                }
            }
        };

        foreach (['object', 'target', 'to', 'cc', 'bto', 'bcc', 'audience', 'inReplyTo', 'inReplyToAtomUri', 'tag'] as $key) {
            $push($payload[$key] ?? null);
        }

        return array_values(array_unique($urls));
    }

    /**
     * @param array<string,mixed> $object
     */
    private static function postFromReply(array $object): ?Post
    {
        foreach (['inReplyTo', 'inReplyToAtomUri'] as $key) {
            foreach (self::idsOf($object[$key] ?? null) as $url) {
                $post = ActivityPub::postFromObjectId($url);
                if ($post !== null) {
                    return $post;
                }
            }
        }
        return null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function embeddedObject(mixed $object): ?array
    {
        if (is_array($object) && (isset($object['type']) || isset($object['content']) || isset($object['inReplyTo']))) {
            return $object;
        }
        $url = self::idOf($object);
        if ($url === '' || !Http::isSafeUrl($url)) {
            return null;
        }
        $fetched = Http::getJson($url);
        return is_array($fetched) ? $fetched : null;
    }

    /** @param array<string,mixed> $object */
    private static function objectContent(array $object): string
    {
        $html = (string) ($object['content'] ?? '');
        if ($html !== '') {
            return $html;
        }
        $map = $object['contentMap'] ?? null;
        if (is_array($map)) {
            foreach ($map as $value) {
                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }
        return (string) ($object['summary'] ?? '');
    }

    private static function typeOf(mixed $value): string
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (is_string($item) && $item !== '') {
                    $value = $item;
                    break;
                }
            }
        }
        if (!is_string($value) || $value === '') {
            return '';
        }
        $value = trim($value);
        if (str_contains($value, '#')) {
            return substr($value, (int) strrpos($value, '#') + 1);
        }
        if (str_contains($value, '/')) {
            return substr($value, (int) strrpos($value, '/') + 1);
        }
        return $value;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function loadRemote(string $actorId, Request $request): ?array
    {
        $signature = Http::parseSignatureHeader($request->header('Signature') ?? '')
            ?? Http::parseSignatureHeader(ltrim(substr($request->header('Authorization') ?? '', 10)))
            ?? [];
        $keyId = (string) ($signature['keyId'] ?? $actorId);
        $fetchUrl = explode('#', $keyId)[0];
        if ($fetchUrl === '') {
            $fetchUrl = $actorId;
        }

        $cached = self::cachedActor($actorId);
        if ($cached !== null && (time() - strtotime((string) $cached['fetched_at'])) < 86400
            && (string) ($cached['public_key'] ?? '') !== '') {
            return $cached;
        }

        $document = Http::getJson($fetchUrl);
        if ($document === null && $fetchUrl !== $actorId) {
            $document = Http::getJson($actorId);
        }
        if ($document === null) {
            return $cached;
        }

        $parsed = self::parseActor($document, $actorId);
        if ($parsed === null) {
            return $cached;
        }
        self::storeActor($parsed);
        return $parsed;
    }

    /**
     * @param array<string,mixed> $document
     * @return array<string,mixed>|null
     */
    private static function parseActor(array $document, string $fallbackId): ?array
    {
        $id = self::idOf($document['id'] ?? $fallbackId);
        $inbox = self::idOf($document['inbox'] ?? '');
        if ($id === '' || $inbox === '' || !Http::isSafeUrl($inbox)) {
            return null;
        }

        $shared = '';
        if (isset($document['endpoints']) && is_array($document['endpoints'])) {
            $shared = self::idOf($document['endpoints']['sharedInbox'] ?? '');
        }

        $publicKey = '';
        if (isset($document['publicKey']) && is_array($document['publicKey'])) {
            $publicKey = (string) ($document['publicKey']['publicKeyPem'] ?? '');
        }

        $handle = null;
        $preferred = (string) ($document['preferredUsername'] ?? '');
        $host = parse_url($id, PHP_URL_HOST);
        if ($preferred !== '' && is_string($host)) {
            $handle = $preferred . '@' . $host;
        }

        $avatar = null;
        if (isset($document['icon'])) {
            $icon = $document['icon'];
            if (is_array($icon)) {
                $avatar = is_string($icon['url'] ?? null) ? (string) $icon['url'] : self::idOf($icon);
            }
        }

        return [
            'actor_id'         => $id,
            'inbox_url'        => $inbox,
            'shared_inbox_url' => Http::isSafeUrl($shared) ? $shared : null,
            'public_key'       => $publicKey,
            'handle'           => $handle,
            'display_name'     => isset($document['name']) ? mb_substr((string) $document['name'], 0, 200) : null,
            'avatar_url'       => is_string($avatar) && Http::isSafeUrl($avatar) ? $avatar : null,
            'fetched_at'       => gmdate('Y-m-d H:i:s'),
        ];
    }

    /** @return array<string,mixed>|null */
    private static function cachedActor(string $actorId): ?array
    {
        Schema::ensure();
        $row = Database::instance()->fetch(
            'SELECT * FROM {{ap_remote_actors}} WHERE actor_id = ?',
            [$actorId]
        );
        return $row;
    }

    /** @param array<string,mixed> $actor */
    private static function storeActor(array $actor): void
    {
        Schema::ensure();
        $db = Database::instance();
        $db->query(
            'INSERT INTO {{ap_remote_actors}}
                (actor_id, inbox_url, shared_inbox_url, public_key, handle, display_name, avatar_url, fetched_at)
             VALUES
                (:actor_id, :inbox_url, :shared_inbox_url, :public_key, :handle, :display_name, :avatar_url, :fetched_at)
             ON DUPLICATE KEY UPDATE
                inbox_url = VALUES(inbox_url),
                shared_inbox_url = VALUES(shared_inbox_url),
                public_key = VALUES(public_key),
                handle = VALUES(handle),
                display_name = VALUES(display_name),
                avatar_url = VALUES(avatar_url),
                fetched_at = VALUES(fetched_at)',
            [
                'actor_id'         => $actor['actor_id'],
                'inbox_url'        => $actor['inbox_url'],
                'shared_inbox_url' => $actor['shared_inbox_url'],
                'public_key'       => $actor['public_key'],
                'handle'           => $actor['handle'],
                'display_name'     => $actor['display_name'],
                'avatar_url'       => $actor['avatar_url'],
                'fetched_at'       => $actor['fetched_at'],
            ]
        );
    }

    private static function idOf(mixed $value): string
    {
        $ids = self::idsOf($value);
        return $ids[0] ?? '';
    }

    /** @return list<string> */
    private static function idsOf(mixed $value): array
    {
        if (is_string($value)) {
            $value = trim($value);
            return $value !== '' ? [$value] : [];
        }
        if (!is_array($value)) {
            return [];
        }
        if (isset($value['id']) && is_string($value['id']) && trim($value['id']) !== '') {
            return [trim($value['id'])];
        }
        if (isset($value['@id']) && is_string($value['@id']) && trim($value['@id']) !== '') {
            return [trim($value['@id'])];
        }
        if (array_is_list($value)) {
            $ids = [];
            foreach ($value as $item) {
                foreach (self::idsOf($item) as $id) {
                    $ids[] = $id;
                }
            }
            return $ids;
        }
        return [];
    }
}
