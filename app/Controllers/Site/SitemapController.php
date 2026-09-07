<?php

declare(strict_types=1);

namespace Noblogs\Controllers\Site;

use Noblogs\Core\Response;
use Noblogs\Core\Url;
use Noblogs\Models\Post;
use Noblogs\Support\Dates;

final class SitemapController extends SiteController
{
    public function index(): Response
    {
        if (!$this->blog->isIndexable()) {
            return Response::xml(
                '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
                . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>'
            )->noIndex();
        }

        $root = rtrim(Url::blogRoot($this->blog), '/');
        $entries = [[
            'loc'     => $root . '/',
            'lastmod' => Dates::parse($this->blog->updated_at),
        ]];

        foreach (Post::published($this->blog, ['pages' => true, 'limit' => 5000]) as $post) {
            if (!$post->make_discoverable) {
                continue;
            }
            $entries[] = [
                'loc'     => $root . '/' . $post->slug . '/',
                'lastmod' => $post->updatedAt(),
            ];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($entries as $entry) {
            $xml .= '  <url>' . "\n"
                . '    <loc>' . htmlspecialchars($entry['loc'], ENT_XML1, 'UTF-8') . '</loc>' . "\n";
            if ($entry['lastmod'] !== null) {
                $xml .= '    <lastmod>' . $entry['lastmod']->format('Y-m-d') . '</lastmod>' . "\n";
            }
            $xml .= '  </url>' . "\n";
        }

        return $this->applyPublicCache(Response::xml($xml . '</urlset>' . "\n"));
    }
}
