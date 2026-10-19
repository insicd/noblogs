<?php

declare(strict_types=1);

namespace Noblogs\Federation;

use Noblogs\Core\Config;
use Noblogs\Core\Request;
use Noblogs\Core\Response;
use Noblogs\Core\Url;
use Noblogs\Markdown\Renderer;
use Noblogs\Models\Blog;
use Noblogs\Models\Post;
use Noblogs\Models\Setting;
use Noblogs\Support\Str;

/**
 * Documenti ActivityPub e indirizzi canonici.
 *
 * L'actor vive sempre sul dominio dell'istanza (`nome@noblogs.it` e
 * `/ap/nome`), indipendentemente da come il blog è raggiunto: percorso,
 * terzo livello o dominio proprio. Così un cambio di routing non rompe
 * i follower già federati.
 */
final class ActivityPub
{
    public const CONTEXT = [
        'https://www.w3.org/ns/activitystreams',
        'https://w3id.org/security/v1',
    ];

    public const PUBLIC = 'https://www.w3.org/ns/activitystreams#Public';

    public static function instanceEnabled(): bool
    {
        $stored = Setting::get('federation.enabled');
        if ($stored !== null) {
            return Setting::bool('federation.enabled', true);
        }
        return (bool) Config::get('federation.enabled', true);
    }

    public static function blogEnabled(Blog $blog): bool
    {
        return self::instanceEnabled()
            && $blog->federation_active
            && $blog->isServable()
            && $blog->isIndexable();
    }

    public static function isProtocolPath(string $path): bool
    {
        $path = '/' . trim($path, '/');
        return str_starts_with($path, '/.well-known/')
            || str_starts_with($path, '/ap/')
            || $path === '/ap'
            || str_starts_with($path, '/nodeinfo/');
    }

    public static function wantsJson(Request $request): bool
    {
        $accept = strtolower($request->header('Accept') ?? '');
        return str_contains($accept, 'application/activity+json')
            || str_contains($accept, 'application/ld+json');
    }

    public static function handle(Blog $blog): string
    {
        return $blog->subdomain . '@' . Url::mainDomain();
    }

    public static function actorId(Blog $blog): string
    {
        return Url::platform('/ap/' . $blog->subdomain);
    }

    public static function inboxUrl(Blog $blog): string
    {
        return self::actorId($blog) . '/inbox';
    }

    public static function sharedInboxUrl(): string
    {
        return Url::platform('/ap/inbox');
    }

    public static function outboxUrl(Blog $blog): string
    {
        return self::actorId($blog) . '/outbox';
    }

    public static function followersUrl(Blog $blog): string
    {
        return self::actorId($blog) . '/followers';
    }

    public static function followingUrl(Blog $blog): string
    {
        return self::actorId($blog) . '/following';
    }

    public static function keyId(Blog $blog): string
    {
        return self::actorId($blog) . '#main-key';
    }

    public static function objectId(Blog $blog, Post $post): string
    {
        return self::actorId($blog) . '/posts/' . $post->uid;
    }

    public static function activityId(Blog $blog, Post $post, string $type): string
    {
        return self::objectId($blog, $post) . '/' . strtolower($type);
    }

    /** @return array<string,mixed> */
    public static function webfinger(Blog $blog): array
    {
        return [
            'subject' => 'acct:' . self::handle($blog),
            'aliases' => [
                Url::blogRoot($blog),
                self::actorId($blog),
            ],
            'links' => [
                [
                    'rel'  => 'http://webfinger.net/rel/profile-page',
                    'type' => 'text/html',
                    'href' => Url::blogRoot($blog),
                ],
                [
                    'rel'  => 'self',
                    'type' => 'application/activity+json',
                    'href' => self::actorId($blog),
                ],
            ],
        ];
    }

    /** @return array<string,mixed>|null */
    public static function actorDocument(Blog $blog): ?array
    {
        $keys = Keys::forBlog($blog);
        if ($keys === null) {
            return null;
        }

        $icon = self::icon($blog);
        $document = [
            '@context' => self::CONTEXT,
            'id' => self::actorId($blog),
            'type' => 'Person',
            'preferredUsername' => $blog->subdomain,
            'name' => $blog->title,
            'summary' => $blog->description(),
            'url' => Url::blogRoot($blog),
            'inbox' => self::inboxUrl($blog),
            'outbox' => self::outboxUrl($blog),
            'followers' => self::followersUrl($blog),
            'following' => self::followingUrl($blog),
            'manuallyApprovesFollowers' => false,
            'discoverable' => $blog->discoverable && $blog->isIndexable(),
            'published' => self::iso($blog->created_at),
            'publicKey' => [
                'id' => self::keyId($blog),
                'owner' => self::actorId($blog),
                'publicKeyPem' => $keys['public'],
            ],
            'endpoints' => [
                'sharedInbox' => self::sharedInboxUrl(),
            ],
        ];
        if ($icon !== null) {
            $document['icon'] = $icon;
        }

        return $document;
    }

    /** @return array<string,mixed> */
    public static function articleDocument(Blog $blog, Post $post): array
    {
        $html = self::html($blog, $post);
        $attachments = self::attachments($html, $blog, $post);
        $document = [
            '@context' => self::CONTEXT,
            'id' => self::objectId($blog, $post),
            'type' => 'Article',
            'published' => self::iso($post->first_published_at ?? $post->published_at),
            'updated' => self::iso($post->updated_at),
            'attributedTo' => self::actorId($blog),
            'to' => [self::PUBLIC],
            'cc' => [self::followersUrl($blog)],
            'name' => $post->title,
            'content' => $html,
            'mediaType' => 'text/html',
            'url' => Url::post($blog, $post->slug),
            'tag' => array_map(static fn(string $tag): array => [
                'type' => 'Hashtag',
                'name' => '#' . $tag,
            ], $post->tagList()),
        ];
        $summary = $post->description();
        if ($summary !== '') {
            $document['summary'] = $summary;
        }
        if ($attachments !== []) {
            $document['attachment'] = $attachments;
        }
        $lang = $post->lang !== null && $post->lang !== '' ? $post->lang : $blog->displayLang();
        if ($lang !== '') {
            $document['contentMap'] = [$lang => $html];
        }

        return $document;
    }

    /** @return array<string,mixed> */
    public static function wrapActivity(Blog $blog, Post $post, string $type): array
    {
        $object = $type === 'Delete'
            ? ['id' => self::objectId($blog, $post), 'type' => 'Tombstone']
            : self::articleDocument($blog, $post);
        unset($object['@context']);

        return [
            '@context' => self::CONTEXT,
            'id' => self::activityId($blog, $post, $type),
            'type' => $type,
            'actor' => self::actorId($blog),
            'published' => self::iso($post->updated_at !== '' ? $post->updated_at : gmdate('Y-m-d H:i:s')),
            'to' => [self::PUBLIC],
            'cc' => [self::followersUrl($blog)],
            'object' => $object,
        ];
    }

    /** @return array<string,mixed> */
    public static function outbox(Blog $blog, int $page = 0): array
    {
        $total = (int) \Noblogs\Core\Database::instance()->fetchColumn(
            'SELECT COUNT(*) FROM {{posts}}
             WHERE blog_id = ? AND is_page = 0 AND is_published = 1 AND hidden = 0
               AND published_at <= ?',
            [$blog->id, gmdate('Y-m-d H:i:s')]
        );
        $id = self::outboxUrl($blog);

        if ($page < 1) {
            return [
                '@context' => self::CONTEXT,
                'id' => $id,
                'type' => 'OrderedCollection',
                'totalItems' => $total,
                'first' => $id . '?page=1',
            ];
        }

        $perPage = 20;
        $offset = ($page - 1) * $perPage;
        $rows = \Noblogs\Core\Database::instance()->fetchAll(
            'SELECT * FROM {{posts}}
             WHERE blog_id = ? AND is_page = 0 AND is_published = 1 AND hidden = 0
               AND published_at <= ?
             ORDER BY published_at DESC
             LIMIT ' . $perPage . ' OFFSET ' . $offset,
            [$blog->id, gmdate('Y-m-d H:i:s')]
        );

        $items = [];
        foreach ($rows as $row) {
            $items[] = self::wrapActivity($blog, Post::hydrate($row), 'Create');
        }

        $document = [
            '@context' => self::CONTEXT,
            'id' => $id . '?page=' . $page,
            'type' => 'OrderedCollectionPage',
            'partOf' => $id,
            'orderedItems' => $items,
        ];
        if ($offset + $perPage < $total) {
            $document['next'] = $id . '?page=' . ($page + 1);
        }
        if ($page > 1) {
            $document['prev'] = $id . '?page=' . ($page - 1);
        }

        return $document;
    }

    /** @return array<string,mixed> */
    public static function followers(Blog $blog, int $page = 0): array
    {
        Schema::ensure();
        $total = ApFollower::count($blog);
        $id = self::followersUrl($blog);
        if ($page < 1) {
            return [
                '@context' => self::CONTEXT,
                'id' => $id,
                'type' => 'OrderedCollection',
                'totalItems' => $total,
                'first' => $id . '?page=1',
            ];
        }

        $ids = [];
        foreach (ApFollower::page($blog, $page, 20) as $follower) {
            $ids[] = $follower->actor_id;
        }

        return [
            '@context' => self::CONTEXT,
            'id' => $id . '?page=' . $page,
            'type' => 'OrderedCollectionPage',
            'partOf' => $id,
            'orderedItems' => $ids,
        ];
    }

    /** @return array<string,mixed> */
    public static function following(Blog $blog): array
    {
        return [
            '@context' => self::CONTEXT,
            'id' => self::followingUrl($blog),
            'type' => 'OrderedCollection',
            'totalItems' => 0,
        ];
    }

    /** @return array<string,mixed> */
    public static function nodeInfo(): array
    {
        Schema::ensure();
        $db = \Noblogs\Core\Database::instance();
        $users = (int) $db->fetchColumn(
            'SELECT COUNT(*) FROM {{blogs}} WHERE hidden = 0 AND reviewed = 1 AND federation_active = 1'
        );
        $posts = (int) $db->fetchColumn(
            'SELECT COUNT(*) FROM {{posts}} p
             INNER JOIN {{blogs}} b ON b.id = p.blog_id
             WHERE p.is_page = 0 AND p.is_published = 1 AND p.hidden = 0
               AND b.hidden = 0 AND b.reviewed = 1'
        );

        return [
            'version' => '2.1',
            'software' => [
                'name'    => 'noblogs',
                'version' => NOBLOGS_VERSION,
            ],
            'protocols' => ['activitypub'],
            'services' => [
                'inbound'  => [],
                'outbound' => ['atom1.0', 'rss2.0'],
            ],
            'openRegistrations' => (bool) (Setting::get('platform.registration_open') !== null
                ? Setting::bool('platform.registration_open', true)
                : Config::get('platform.registration_open', true)),
            'usage' => [
                'users' => [
                    'total' => $users,
                ],
                'localPosts' => $posts,
            ],
            'metadata' => [
                'nodeName' => (string) (Setting::get('site.name') ?: Config::get('site.name', 'Noblogs')),
            ],
        ];
    }

    public static function json(mixed $data, int $status = 200): Response
    {
        $encoded = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return (new Response($encoded === false ? '{}' : $encoded, $status))
            ->withHeader('Content-Type', 'application/activity+json; charset=UTF-8')
            ->withHeader('Access-Control-Allow-Origin', '*');
    }

    public static function jrd(mixed $data, int $status = 200): Response
    {
        $encoded = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return (new Response($encoded === false ? '{}' : $encoded, $status))
            ->withHeader('Content-Type', 'application/jrd+json; charset=UTF-8')
            ->withHeader('Access-Control-Allow-Origin', '*');
    }

    public static function blogFromPath(string $subdomain): ?Blog
    {
        $blog = Blog::findBySubdomain($subdomain);
        if ($blog === null || !self::blogEnabled($blog)) {
            return null;
        }
        return $blog;
    }

    public static function postFromObjectId(string $url): ?Post
    {
        $parts = parse_url($url);
        $path = rtrim((string) ($parts['path'] ?? ''), '/');
        if (preg_match('#^/ap/([a-z0-9][a-z0-9-]{0,62})/posts/([a-z0-9]+)(?:/[a-z0-9-]+)?$#', $path, $m) !== 1) {
            return self::postFromHtmlUrl($url);
        }
        $blog = Blog::findBySubdomain($m[1]);
        if ($blog === null) {
            return null;
        }
        return Post::findByUid($m[2]);
    }

    public static function blogFromUrl(string $url): ?Blog
    {
        $parts = parse_url($url);
        $path = rtrim((string) ($parts['path'] ?? ''), '/');
        if (preg_match('#^/ap/([a-z0-9][a-z0-9-]{0,62})(?:/.*)?$#', $path, $m) === 1
            && !in_array($m[1], ['inbox', 'outbox', 'followers', 'following'], true)
        ) {
            $blog = Blog::findBySubdomain($m[1]);
            if ($blog !== null) {
                return $blog;
            }
        }
        $post = self::postFromObjectId($url);
        return $post !== null ? Blog::find($post->blog_id) : null;
    }

    public static function postFromHtmlUrl(string $url): ?Post
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = trim((string) ($parts['path'] ?? ''), '/');
        $main = strtolower(Url::mainDomain());
        $extra = array_map('strtolower', (array) Config::get('site.extra_hosts', []));

        $blog = null;
        $slug = $path;
        if ($host === $main || $host === 'www.' . $main || in_array($host, $extra, true)) {
            $segments = $path === '' ? [] : explode('/', $path);
            $first = $segments[0] ?? '';
            if ($first !== '' && !\Noblogs\Core\Tenant::isReservedSegment($first)) {
                $blog = Blog::findBySubdomain($first);
                $slug = implode('/', array_slice($segments, 1));
            }
        } elseif (str_ends_with($host, '.' . $main)) {
            $sub = substr($host, 0, -strlen('.' . $main));
            if ($sub !== '' && !str_contains($sub, '.')) {
                $blog = Blog::findBySubdomain($sub);
            }
        } else {
            $blog = Blog::findByDomain($host);
        }

        if ($blog === null) {
            return null;
        }
        $slug = mb_strtolower(trim($slug, '/'));
        if ($slug === '') {
            return null;
        }
        $post = Post::findBySlug($blog, $slug) ?? Post::findByAlias($blog, $slug);
        if ($post === null || $post->blog_id !== $blog->id) {
            return null;
        }
        return $post;
    }

    public static function iso(string $datetime): string
    {
        try {
            return (new \DateTimeImmutable($datetime, new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
        } catch (\Exception) {
            return gmdate('Y-m-d\TH:i:s\Z');
        }
    }

    private static function html(Blog $blog, Post $post): string
    {
        $html = Renderer::post($blog, $post);
        $origin = rtrim(Url::blogRoot($blog), '/');

        $rewrite = static function (string $url) use ($origin): string {
            if ($url === '' || str_starts_with($url, '#') || str_starts_with($url, '//')) {
                return $url;
            }
            if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $url) === 1) {
                return $url;
            }
            if (str_starts_with($url, '/')) {
                // I media stanno sul dominio principale, non sul blog.
                if (str_starts_with($url, '/media/') || str_starts_with($url, '/assets/')) {
                    return rtrim(Url::platform(), '/') . $url;
                }
                return $origin . $url;
            }
            return $url;
        };

        $html = preg_replace_callback(
            '/\b(href|src|poster)=(["\'])([^"\']*)\2/i',
            static fn(array $m): string => $m[1] . '=' . $m[2] . $rewrite($m[3]) . $m[2],
            $html
        ) ?? $html;

        return $html;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function attachments(string $html, Blog $blog, Post $post): array
    {
        $seen = [];
        $items = [];
        $push = static function (string $url, string $type) use (&$seen, &$items): void {
            if ($url === '' || isset($seen[$url])) {
                return;
            }
            $seen[$url] = true;
            $mime = match (true) {
                $type === 'audio' => 'audio/mpeg',
                $type === 'video' => 'video/mp4',
                str_ends_with(strtolower($url), '.png') => 'image/png',
                str_ends_with(strtolower($url), '.gif') => 'image/gif',
                str_ends_with(strtolower($url), '.webp') => 'image/webp',
                str_ends_with(strtolower($url), '.svg') => 'image/svg+xml',
                default => 'image/jpeg',
            };
            $asType = $type === 'audio' ? 'Document' : ($type === 'video' ? 'Document' : 'Image');
            $items[] = [
                'type' => $asType,
                'mediaType' => $mime,
                'url' => $url,
            ];
        };

        if (preg_match_all('/<img\b[^>]*\bsrc=(["\'])([^"\']+)\1/i', $html, $matches) > 0) {
            foreach ($matches[2] as $src) {
                $push($src, 'image');
            }
        }
        if (preg_match_all('/<(audio|video)\b[^>]*\bsrc=(["\'])([^"\']+)\2/i', $html, $matches, PREG_SET_ORDER) > 0) {
            foreach ($matches as $match) {
                $push($match[3], strtolower($match[1]));
            }
        }
        if (is_string($post->meta_image) && $post->meta_image !== '') {
            $url = $post->meta_image;
            if (!preg_match('#^https?://#i', $url)) {
                $url = rtrim(Url::blogRoot($blog), '/') . '/' . ltrim($url, '/');
            }
            $push($url, 'image');
        }

        return $items;
    }

    /** @return array<string,string>|null */
    private static function icon(Blog $blog): ?array
    {
        $favicon = trim($blog->favicon);
        if ($favicon === '' || Str::isSingleGlyph($favicon)) {
            return null;
        }
        if (!preg_match('#^https?://#i', $favicon)) {
            return null;
        }
        return [
            'type' => 'Image',
            'url' => $favicon,
        ];
    }
}
