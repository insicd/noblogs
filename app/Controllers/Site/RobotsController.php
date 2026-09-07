<?php

declare(strict_types=1);

namespace Noblogs\Controllers\Site;

use Noblogs\Core\Response;
use Noblogs\Core\Url;

final class RobotsController extends SiteController
{
    public function index(): Response
    {
        $body = $this->blog->robotsTxt();

        if ($this->blog->isIndexable()) {
            $body .= "\nSitemap: " . rtrim(Url::blogRoot($this->blog), '/') . "/sitemap.xml\n";
        }

        return $this->applyPublicCache(Response::text($body));
    }
}
