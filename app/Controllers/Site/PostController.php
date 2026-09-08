<?php

declare(strict_types=1);

namespace Noblogs\Controllers\Site;

use Noblogs\Core\Response;
use Noblogs\Core\Url;
use Noblogs\Markdown\Renderer;
use Noblogs\Models\Post;
use Noblogs\Models\Redirect;

/**
 * Risoluzione di un percorso arbitrario dentro un blog.
 *
 * L'ordine dei tentativi è: reindirizzamento esplicito, slug del post, vecchio
 * indirizzo di un post, e infine pagina non trovata.
 */
final class PostController extends SiteController
{
    public function show(string $path): Response
    {
        $slug = mb_strtolower(trim($path, '/'));

        if ($slug === '' || mb_strlen($slug) > 200) {
            return $this->notFound();
        }

        $redirect = Redirect::match($this->blog, $slug);
        if ($redirect !== null) {
            return $this->redirect($redirect->to_url, $redirect->status_code);
        }

        $post = Post::findBySlug($this->blog, $slug);

        if ($post === null) {
            $aliased = Post::findByAlias($this->blog, $slug);
            if ($aliased !== null) {
                return $this->redirect(Url::site('/' . $aliased->slug . '/'), 301);
            }
            return $this->notFound();
        }

        if (!$post->isVisible()) {
            // Una bozza resta raggiungibile con il suo link di anteprima: è
            // così che si fa rileggere un articolo prima di pubblicarlo.
            if (!$post->matchesPreviewToken($this->request->query('token'))) {
                return $this->notFound();
            }
        }

        return $this->renderPost($post);
    }

    /** Percorso che nessuna rotta ha riconosciuto. */
    public function missing(): Response
    {
        return $this->notFound();
    }

    private function renderPost(Post $post): Response
    {
        $isDraft = !$post->isVisible();
        $lang = $post->lang !== null && $post->lang !== '' ? $post->lang : $this->blog->displayLang();

        return $this->page('site/post', [
            'bodyClass'   => trim(($post->is_page ? 'page' : 'post') . ' ' . ($post->class_name ?? '')),
            'post'        => $post,
            'contentHtml' => Renderer::post($this->blog, $post),
            'pageTitle'   => $post->title . ' — ' . $this->blog->title,
            'description' => $post->description(),
            'metaImage'   => $post->meta_image ?? $this->blog->meta_image,
            'canonical'   => $post->canonical_url !== null && $post->canonical_url !== ''
                ? $post->canonical_url
                : Url::post($this->blog, $post->slug),
            'lang'        => $lang,
            'showMeta'    => !$post->is_page,
            // Il markup sta sempre nell'HTML degli articoli pubblicati.
            // Se gli apprezzamenti sono accesi il pulsante è visibile da subito;
            // lo script lo nasconde solo se nel frattempo sono stati spenti.
            'showUpvote'      => !$post->is_page && !$isDraft,
            'upvotesEnabled'  => $this->blog->upvotes_active,
            'isDraft'     => $isDraft,
            'indexable'   => $this->blog->isIndexable() && !$isDraft && $post->make_discoverable,
            'trackPath'   => $isDraft ? null : $post->uid,
        ]);
    }
}
