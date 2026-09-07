<?php

declare(strict_types=1);

namespace Noblogs\Controllers\Dashboard;

use Noblogs\Core\Response;
use Noblogs\Core\Url;
use Noblogs\Markdown\Renderer;
use Noblogs\Models\Blog;
use Noblogs\Models\Post;
use Noblogs\Support\Dates;
use Noblogs\Support\Str;

/**
 * Elenco, scrittura e cancellazione di articoli e pagine.
 */
final class PostController extends DashboardController
{
    public function index(string $blog): Response
    {
        return $this->listing($blog, false);
    }

    public function pages(string $blog): Response
    {
        return $this->listing($blog, true);
    }

    public function create(string $blog): Response
    {
        $found = $this->openBlog($blog);
        if ($found instanceof Response) {
            return $found;
        }

        $post = Post::make($found);
        $post->is_page = $this->request->boolean('pagina');
        $post->content = $found->post_template;

        if ($this->request->isPost()) {
            if ($found->isAtPostLimit()) {
                return $this->forbidden(__('post.error.blog_full', [
                    'max' => $this->limit('posts_per_blog', 5000),
                ]));
            }
            return $this->store($found, $post, $this->newUrl($found));
        }

        return $this->form($found, $post);
    }

    public function edit(string $blog, string $id): Response
    {
        $found = $this->openBlog($blog);
        if ($found instanceof Response) {
            return $found;
        }

        $post = Post::findForBlog($found, (int) $id);
        if ($post === null) {
            return $this->notFound(__('post.error.missing'));
        }

        if ($this->request->isPost()) {
            return $this->store($found, $post, $this->blogUrl($found, '/articoli/' . $post->id));
        }

        return $this->form($found, $post);
    }

    public function destroy(string $blog, string $id): Response
    {
        $found = $this->openBlog($blog);
        if ($found instanceof Response) {
            return $found;
        }

        $csrf = $this->requireCsrf();
        if ($csrf !== null) {
            return $csrf;
        }

        $post = Post::findForBlog($found, (int) $id);
        if ($post === null) {
            return $this->notFound(__('post.error.missing'));
        }

        $wasPage = $post->is_page;
        $title = $post->title;
        $post->delete();

        // Tag e contatori derivano dai post pubblicati: dopo una cancellazione
        // vanno ricalcolati, altrimenti la nuvola dei tag mostra etichette che
        // non portano più da nessuna parte.
        $found->refreshTags();
        $found->refreshCounters();

        return $this->succeed(
            $this->blogUrl($found, $wasPage ? '/pagine' : '/articoli'),
            __('post.deleted', ['title' => $title])
        );
    }

    public function duplicate(string $blog, string $id): Response
    {
        $found = $this->openBlog($blog);
        if ($found instanceof Response) {
            return $found;
        }

        $csrf = $this->requireCsrf();
        if ($csrf !== null) {
            return $csrf;
        }

        $original = Post::findForBlog($found, (int) $id);
        if ($original === null) {
            return $this->notFound(__('post.error.missing'));
        }
        if ($found->isAtPostLimit()) {
            return $this->forbidden(__('post.error.blog_full', [
                'max' => $this->limit('posts_per_blog', 5000),
            ]));
        }

        $copy = Post::make($found);
        $copy->title = mb_substr($original->title . ' ' . __('post.copy_suffix'), 0, 200);
        $copy->slug = Post::uniqueSlug($found, $copy->title);
        $copy->content = $original->content;
        $copy->is_page = $original->is_page;
        $copy->tags = $original->tags;
        $copy->meta_description = $original->meta_description;
        $copy->meta_image = $original->meta_image;
        $copy->lang = $original->lang;
        $copy->class_name = $original->class_name;
        $copy->make_discoverable = $original->make_discoverable;
        // La copia nasce come bozza: due articoli identici pubblicati insieme
        // non servono a nessuno, e l'alias non si può duplicare.
        $copy->is_published = false;
        $copy->alias = null;
        $copy->save();

        $found->refreshTags();
        $found->refreshCounters();

        return $this->succeed(
            $this->blogUrl($found, '/articoli/' . $copy->id),
            __('post.duplicated')
        );
    }

    /**
     * Anteprima del markdown, chiamata dall'editor. Restituisce il frammento
     * di HTML reso con le stesse regole della pagina pubblica: se il blog non
     * può scrivere HTML grezzo, l'anteprima lo mostra già ripulito.
     */
    public function preview(string $blog): Response
    {
        $found = $this->openBlog($blog);
        if ($found instanceof Response) {
            return $found;
        }

        // Il token scaduto lascia il messaggio in coda: l'utente lo leggerà al
        // prossimo caricamento di pagina, e l'editor mostra il proprio avviso.
        $csrf = $this->requireCsrf();
        if ($csrf !== null) {
            return Response::html('', 403)->noCache();
        }

        $markdown = $this->request->input('content', '') ?? '';
        $maxChars = $this->limit('post_max_chars', 1000000);
        if (mb_strlen($markdown) > $maxChars) {
            $markdown = mb_substr($markdown, 0, $maxChars);
        }

        $post = null;
        $id = $this->request->int('id');
        if ($id > 0) {
            $post = Post::findForBlog($found, $id);
        }

        return Response::html(Renderer::content($found, $markdown, $post))->noCache()->noIndex();
    }

    // -----------------------------------------------------------------------
    // Interno
    // -----------------------------------------------------------------------

    private function listing(string $blog, bool $pages): Response
    {
        $found = $this->openBlog($blog);
        if ($found instanceof Response) {
            return $found;
        }

        $posts = Post::forDashboard($found, $pages);
        $status = $this->request->query('stato', '') ?? '';
        $query = trim($this->request->query('q', '') ?? '');

        $counts = ['all' => count($posts), 'published' => 0, 'draft' => 0, 'scheduled' => 0];
        foreach ($posts as $post) {
            if ($post->isDraft()) {
                $counts['draft']++;
            } elseif ($post->isScheduled()) {
                $counts['scheduled']++;
            } else {
                $counts['published']++;
            }
        }

        $visible = array_values(array_filter($posts, static function (Post $post) use ($status, $query): bool {
            $matchesStatus = match ($status) {
                'bozze'        => $post->isDraft(),
                'programmati'  => $post->isScheduled(),
                'pubblicati'   => $post->isVisible(),
                default        => true,
            };
            if (!$matchesStatus) {
                return false;
            }
            if ($query === '') {
                return true;
            }
            $needle = mb_strtolower($query);
            return str_contains(mb_strtolower($post->title), $needle)
                || str_contains(mb_strtolower($post->slug), $needle);
        }));

        return $this->panel('dashboard/posts', [
            'pageTitle' => $pages ? __('post.pages_title') : __('post.posts_title'),
            'section'   => $pages ? 'pages' : 'posts',
            'blog'      => $found,
            'posts'     => $visible,
            'counts'    => $counts,
            'isPages'   => $pages,
            'status'    => $status,
            'query'     => $query,
            'newUrl'    => $this->newUrl($found, $pages),
        ]);
    }

    private function form(Blog $blog, Post $post): Response
    {
        $timezone = $this->account()->timezone;
        $published = $post->publishedAt() ?? Dates::now();

        return $this->panel('dashboard/post-form', [
            'pageTitle'   => $post->exists() ? $post->title : __('post.new_title'),
            'section'     => $post->is_page ? 'pages' : 'posts',
            'blog'        => $blog,
            'post'        => $post,
            'publishedAt' => Dates::toLocal($published, $timezone)->format('Y-m-d\TH:i'),
            'timezone'    => $timezone,
            'media'       => $this->mediaCatalogue($blog),
            'labels'      => $this->editorLabels(),
            'previewUrl'  => $post->exists()
                ? Url::post($blog, $post->slug) . '?token=' . $post->previewToken()
                : null,
            'publicUrl'   => $post->exists() ? Url::post($blog, $post->slug) : null,
            'action'      => $post->exists()
                ? $this->blogUrl($blog, '/articoli/' . $post->id)
                : $this->newUrl($blog, $post->is_page),
        ]);
    }

    /** Salvataggio comune a creazione e modifica. */
    private function store(Blog $blog, Post $post, string $returnUrl): Response
    {
        $csrf = $this->requireCsrf();
        if ($csrf !== null) {
            return $csrf;
        }

        $title = $this->request->trimmed('title');
        $content = $this->request->input('content', '') ?? '';
        $maxChars = $this->limit('post_max_chars', 1000000);

        if ($title === '') {
            return $this->withInput($returnUrl, $this->request->post, __('post.error.title_required'));
        }
        if (mb_strlen($content) > $maxChars) {
            return $this->withInput($returnUrl, $this->request->post, __('post.error.too_long', ['max' => $maxChars]));
        }

        $canonical = $this->request->trimmed('canonical_url');
        if ($canonical !== '' && !Str::isHttpUrl($canonical)) {
            return $this->withInput($returnUrl, $this->request->post, __('post.error.canonical'));
        }

        $desiredSlug = $this->request->trimmed('slug');
        $slug = Post::uniqueSlug($blog, $desiredSlug !== '' ? $desiredSlug : $title, $post->id);

        $alias = Str::slug($this->request->trimmed('alias'), '-', true);
        if ($alias === $slug) {
            // Un alias uguale allo slug renderebbe il post irraggiungibile
            // tramite un redirect verso se stesso.
            $alias = '';
        }

        $publishedAt = Dates::fromLocal($this->request->trimmed('published_at'), $this->account()->timezone)
            ?? Dates::now();

        $lang = mb_strtolower($this->request->trimmed('lang'));
        if ($lang !== '' && !preg_match('/^[a-z]{2}(-[a-z]{2})?$/', $lang)) {
            $lang = '';
        }

        $post->fill([
            'title'             => mb_substr($title, 0, 200),
            'slug'              => $slug,
            'alias'             => $alias !== '' ? mb_substr($alias, 0, 200) : null,
            'content'           => $content,
            'is_page'           => $this->request->boolean('is_page'),
            'is_published'      => $this->request->boolean('is_published'),
            'make_discoverable' => $this->request->boolean('make_discoverable'),
            'canonical_url'     => $canonical !== '' ? mb_substr($canonical, 0, 300) : null,
            'meta_description'  => mb_substr($this->request->trimmed('meta_description'), 0, 300) ?: null,
            'meta_image'        => mb_substr($this->request->trimmed('meta_image'), 0, 300) ?: null,
            'lang'              => $lang !== '' ? $lang : null,
            'class_name'        => $this->cssClasses($this->request->trimmed('class_name')),
            'published_at'      => $publishedAt->format('Y-m-d H:i:s'),
        ]);
        $post->setTags(Str::tags($this->request->trimmed('tags')));
        $post->save();

        $blog->refreshTags();
        $blog->refreshCounters();

        return $this->succeed(
            $this->blogUrl($blog, '/articoli/' . $post->id),
            $post->isDraft() ? __('post.saved_draft') : __('post.saved')
        );
    }

    private function newUrl(Blog $blog, bool $pages = false): string
    {
        return $this->blogUrl($blog, '/articoli/nuovo') . ($pages ? '?pagina=1' : '');
    }

    /** Solo nomi di classe: qualunque altro carattere uscirebbe dall'attributo. */
    private function cssClasses(string $value): ?string
    {
        $clean = trim((string) preg_replace('/[^A-Za-z0-9 _-]/', '', $value));
        return $clean !== '' ? mb_substr($clean, 0, 200) : null;
    }
}
