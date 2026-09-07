<?php

declare(strict_types=1);

namespace Noblogs\Controllers\Site;

use Noblogs\Core\Response;
use Noblogs\Core\Url;
use Noblogs\Markdown\Renderer;
use Noblogs\Models\Post;

/**
 * Elenco cronologico degli articoli, con filtro per tag e paginazione.
 */
final class ArchiveController extends SiteController
{
    private const PER_PAGE = 25;

    public function index(): Response
    {
        // Se esiste una pagina con lo slug dell'elenco, la si preferisce:
        // permette di sostituire l'archivio con una versione scritta a mano.
        $override = Post::findBySlug($this->blog, trim($this->blog->blog_path, '/'));
        if ($override !== null && $override->is_page && $override->isVisible()) {
            return $this->page('site/post', [
                'bodyClass'   => 'blog page',
                'post'        => $override,
                'contentHtml' => Renderer::post($this->blog, $override),
                'pageTitle'   => $override->title . ' — ' . $this->blog->title,
                'trackPath'   => $override->uid,
            ]);
        }

        [$includeTags, $excludeTags] = $this->requestedTags();
        $page = $this->pageNumber();

        $options = [
            'tags'         => $includeTags,
            'exclude_tags' => $excludeTags,
            'limit'        => self::PER_PAGE,
            'offset'       => ($page - 1) * self::PER_PAGE,
        ];

        $posts = Post::published($this->blog, $options);
        $total = Post::countPublished($this->blog, [
            'tags'         => $includeTags,
            'exclude_tags' => $excludeTags,
        ]);
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));

        if ($page > $lastPage && $page > 1) {
            return $this->redirect(Url::site('/' . trim($this->blog->blog_path, '/') . '/'), 302);
        }

        $title = $includeTags !== []
            ? __('blog.posts_tagged', ['tags' => implode(', ', $includeTags)])
            : __('blog.all_posts');

        return $this->page('site/archive', [
            'bodyClass'    => $includeTags !== [] ? 'blog tag' : 'blog',
            'posts'        => $posts,
            'heading'      => $title,
            'pageTitle'    => $title . ' — ' . $this->blog->title,
            'includeTags'  => $includeTags,
            'excludeTags'  => $excludeTags,
            'currentPage'  => $page,
            'lastPage'     => $lastPage,
            'total'        => $total,
            'availableTags' => $this->blog->tags(),
            // Le pagine filtrate e quelle oltre la prima non aggiungono nulla
            // agli indici: sono combinazioni degli stessi articoli.
            'indexable'    => $this->blog->isIndexable() && $page === 1 && $includeTags === [],
            'trackPath'    => '',
        ]);
    }
}
