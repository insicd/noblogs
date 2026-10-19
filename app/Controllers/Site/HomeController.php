<?php

declare(strict_types=1);

namespace Noblogs\Controllers\Site;

use Noblogs\Core\Response;
use Noblogs\Core\Url;
use Noblogs\Federation\ActivityPub;
use Noblogs\Markdown\Renderer;
use Noblogs\Models\Post;

/**
 * Homepage di un blog.
 */
final class HomeController extends SiteController
{
    public function index(): Response
    {
        if (ActivityPub::wantsJson($this->request) && ActivityPub::blogEnabled($this->blog)) {
            $document = ActivityPub::actorDocument($this->blog);
            return $document === null
                ? Response::text('Not found', 503)
                : ActivityPub::json($document);
        }

        // Una pagina il cui slug è vuoto o "home" ha la precedenza sul
        // contenuto della homepage: serve a chi vuole gestirla come le altre.
        $homePage = Post::findBySlug($this->blog, 'home');
        if ($homePage !== null && $homePage->is_page && $homePage->isVisible()) {
            return $this->page('site/post', [
                'bodyClass'   => 'home page',
                'post'        => $homePage,
                'contentHtml' => Renderer::post($this->blog, $homePage),
                'pageTitle'   => $this->blog->title,
                'showMeta'    => false,
                'trackPath'   => $homePage->uid,
            ]);
        }

        $content = trim($this->blog->content ?? '');

        // Homepage vuota: si mostra direttamente l'elenco degli articoli,
        // che è quasi sempre quello che serve a chi comincia.
        $contentHtml = $content !== ''
            ? Renderer::content($this->blog, $content)
            : Renderer::content($this->blog, '{{ posts|limit:20 }}');

        return $this->page('site/home', [
            'bodyClass'   => 'home',
            'contentHtml' => $contentHtml,
            'canonical'   => Url::blogRoot($this->blog) . '/',
            'trackPath'   => '',
        ]);
    }
}
