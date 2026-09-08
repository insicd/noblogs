<?php

declare(strict_types=1);

namespace Noblogs\Controllers\Site;

use Noblogs\Core\Config;
use Noblogs\Core\Controller;
use Noblogs\Core\I18n;
use Noblogs\Core\Request;
use Noblogs\Core\Response;
use Noblogs\Core\Tenant;
use Noblogs\Core\Url;
use Noblogs\Core\View;
use Noblogs\Markdown\Renderer;
use Noblogs\Models\Blog;
use Noblogs\Models\Theme;

/**
 * Base dei controller che servono le pagine pubbliche di un blog.
 *
 * Si occupa di ciò che tutte le pagine hanno in comune: il tema, la barra di
 * navigazione, i metadati sociali e le intestazioni di cache.
 */
abstract class SiteController extends Controller
{
    protected Blog $blog;

    public function __construct(Request $request, Tenant $tenant)
    {
        parent::__construct($request, $tenant);
        $this->blog = $tenant->blog();

        // L'interfaccia di contorno (etichette dei pulsanti, messaggi) segue
        // la lingua del blog, non quella della piattaforma.
        I18n::load($this->blog->locale());
    }

    /**
     * Compone una pagina del blog.
     *
     * @param array<string,mixed> $data
     */
    protected function page(string $template, array $data = [], int $status = 200): Response
    {
        $data += [
            'blog'        => $this->blog,
            'bodyClass'   => 'page',
            'stylesheet'  => Theme::stylesheetFor($this->blog),
            'navHtml'     => Renderer::nav($this->blog),
            'titleHtml'   => Renderer::inline($this->blog->title),
            'pageTitle'   => $this->blog->title,
            'description' => $this->blog->description(),
            'canonical'   => Url::blog($this->blog, $this->request->path),
            'metaImage'   => $this->blog->meta_image,
            'indexable'   => $this->blog->isIndexable(),
            'trackPath'   => null,
        ];

        $html = Url::localizeHtml(View::make($template, $data + ['tenant' => $this->tenant]));
        $response = Response::html($html, $status);

        return $status === 200
            ? $this->applyPublicCache($response)
            : $response->noCache();
    }

    /**
     * Le pagine pubbliche si possono conservare a lungo su una cache condivisa
     * ma mai nel browser: così una correzione appare subito a chi ritorna, e
     * l'invalidazione resta nelle mani di chi gestisce il server.
     */
    protected function applyPublicCache(Response $response): Response
    {
        if ($this->request->hasQuery('token')) {
            // Le anteprime delle bozze non vanno mai in cache.
            return $response->noCache()->noIndex();
        }

        $seconds = (int) Config::get('site.cache_seconds', 300);
        $response = $response->cachePublic($seconds, 'blog-' . $this->blog->id);

        if (!$this->blog->isIndexable()) {
            $response = $response->noIndex();
        }

        return $response;
    }

    /** Pagina non trovata, resa con il tema del blog. */
    protected function notFound(string $message = ''): Response
    {
        return $this->page('site/not-found', [
            'bodyClass' => 'not-found',
            'pageTitle' => __('site.not_found_title') . ' — ' . $this->blog->title,
            'message'   => $message,
            'indexable' => false,
        ], 404);
    }

    /**
     * Numero di pagina richiesto, sempre almeno 1 e con un tetto che evita di
     * far scandire a un robot decine di migliaia di pagine vuote.
     */
    protected function pageNumber(): int
    {
        return max(1, min(1000, $this->request->int('pagina', $this->request->int('page', 1))));
    }

    /**
     * Tag richiesti nella query string, con il prefisso meno per escluderli:
     * ?tag=appunti,-privato
     *
     * @return array{0:list<string>,1:list<string>}
     */
    protected function requestedTags(): array
    {
        $raw = $this->request->query('tag') ?? $this->request->query('q') ?? '';
        if (trim($raw) === '') {
            return [[], []];
        }

        $include = [];
        $exclude = [];
        foreach (\Noblogs\Support\Str::tags($raw) as $tag) {
            if (str_starts_with($tag, '-')) {
                $exclude[] = mb_substr($tag, 1);
            } else {
                $include[] = $tag;
            }
        }

        return [
            array_slice($include, 0, 5),
            array_slice(array_values(array_filter($exclude, 'strlen')), 0, 5),
        ];
    }
}
