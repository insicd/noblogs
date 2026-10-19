<?php

declare(strict_types=1);

namespace Noblogs\Controllers\Federation;

use Noblogs\Core\Controller;
use Noblogs\Core\Response;
use Noblogs\Core\Url;
use Noblogs\Federation\ActivityPub;
use Noblogs\Models\Blog;

/**
 * WebFinger, host-meta e NodeInfo: i tre punti da cui Mastodon e gli altri
 * scoprono che un handle `nome@istanza` è un actor ActivityPub.
 */
final class DiscoveryController extends Controller
{
    public function webfinger(): Response
    {
        if (!ActivityPub::instanceEnabled()) {
            return Response::text('Not found', 404);
        }

        $resource = trim((string) ($this->request->query('resource') ?? ''));
        $blog = $this->resolve($resource);
        if ($blog === null) {
            return ActivityPub::jrd(['error' => 'not found'], 404);
        }

        return ActivityPub::jrd(ActivityPub::webfinger($blog))->cachePublic(300);
    }

    public function hostMeta(): Response
    {
        $template = Url::platform('/.well-known/webfinger') . '?resource={uri}';
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<XRD xmlns="http://docs.oasis-open.org/ns/xri/xrd-1.0">' . "\n"
            . '  <Link rel="lrdd" type="application/xrd+xml" template="' . htmlspecialchars($template, ENT_QUOTES) . '"/>' . "\n"
            . '</XRD>' . "\n";

        return Response::xml($xml)->cachePublic(3600);
    }

    public function nodeInfoIndex(): Response
    {
        return Response::json([
            'links' => [[
                'rel'  => 'http://nodeinfo.diaspora.software/ns/schema/2.1',
                'href' => Url::platform('/nodeinfo/2.1'),
            ]],
        ])->withHeader('Access-Control-Allow-Origin', '*')->cachePublic(3600);
    }

    public function nodeInfo(): Response
    {
        if (!ActivityPub::instanceEnabled()) {
            return Response::json(['error' => 'disabled'], 404);
        }

        return Response::json(ActivityPub::nodeInfo())
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->cachePublic(300);
    }

    private function resolve(string $resource): ?Blog
    {
        if ($resource === '') {
            return null;
        }

        if (str_starts_with($resource, 'acct:')) {
            $resource = substr($resource, 5);
        }
        $resource = ltrim($resource, '@');

        if (str_contains($resource, '@') && !str_starts_with($resource, 'http')) {
            [$name, $host] = array_pad(explode('@', $resource, 2), 2, '');
            $host = strtolower($host);
            $main = strtolower(Url::mainDomain());
            $extra = array_map('strtolower', (array) \Noblogs\Core\Config::get('site.extra_hosts', []));
            if ($host !== $main && $host !== 'www.' . $main && !in_array($host, $extra, true)) {
                return null;
            }
            return ActivityPub::blogFromPath($name);
        }

        if (preg_match('#/ap/([a-z0-9][a-z0-9-]{0,62})(?:/|$)#', $resource, $m) === 1) {
            return ActivityPub::blogFromPath($m[1]);
        }

        $parts = parse_url($resource);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = trim((string) ($parts['path'] ?? ''), '/');
        $main = strtolower(Url::mainDomain());

        if (str_ends_with($host, '.' . $main)) {
            $sub = substr($host, 0, -strlen('.' . $main));
            if ($sub !== '' && !str_contains($sub, '.')) {
                return ActivityPub::blogFromPath($sub);
            }
        }
        if (($host === $main || $host === 'www.' . $main) && $path !== '') {
            $first = explode('/', $path)[0];
            return ActivityPub::blogFromPath($first);
        }
        if ($host !== '' && $host !== $main) {
            $blog = Blog::findByDomain($host);
            if ($blog !== null && ActivityPub::blogEnabled($blog)) {
                return $blog;
            }
        }

        return null;
    }
}
