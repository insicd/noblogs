<?php

declare(strict_types=1);

namespace Noblogs\Controllers\Dashboard;

use Noblogs\Core\RateLimiter;
use Noblogs\Core\Response;
use Noblogs\Core\Url;
use Noblogs\Models\Blog;
use Noblogs\Models\Hit;
use Noblogs\Models\Media;
use Noblogs\Models\Post;
use Noblogs\Models\Subscriber;
use Noblogs\Support\Dates;

/**
 * Creazione di un blog, riepilogo e modifica della homepage.
 */
final class BlogController extends DashboardController
{
    public function create(): Response
    {
        $guard = $this->requireAuth(true);
        if ($guard !== null) {
            return $guard;
        }

        $user = $this->account();
        $canCreate = $user->canCreateBlog();

        if ($this->request->isPost()) {
            if (!$canCreate) {
                return $this->forbidden(__('blog.create.limit_reached', ['max' => $user->max_blogs]));
            }

            $csrf = $this->requireCsrf();
            if ($csrf !== null) {
                return $csrf;
            }

            // Un blog nuovo comporta una cartella, un sottodominio e una voce
            // nella coda di revisione: vale la pena limitare i tentativi.
            if (RateLimiter::tooManyAttempts('blog-create:' . $user->id, 5, 3600)) {
                return $this->withInput(
                    Url::to('/dashboard/nuovo-blog'),
                    $this->request->post,
                    __('error.rate_limited')
                );
            }

            $subdomain = mb_strtolower($this->request->trimmed('subdomain'));
            $title = $this->request->trimmed('title');

            $error = Blog::validateSubdomain($subdomain);
            if ($error === null && $title === '') {
                $error = __('blog.create.title_required');
            }
            if ($error !== null) {
                return $this->withInput(Url::to('/dashboard/nuovo-blog'), $this->request->post, $error);
            }

            $blog = Blog::create(
                $user,
                $subdomain,
                $title,
                Blog::starterContent($title, $user->locale)
            );

            return $this->succeed(
                $this->blogUrl($blog),
                __('blog.create.done', ['address' => Url::blogRoot($blog)])
            );
        }

        return $this->panel('dashboard/blog-create', [
            'pageTitle' => __('blog.create.title'),
            'section'   => 'new-blog',
            'canCreate' => $canCreate,
            'maxBlogs'  => $user->max_blogs,
            'blogCount' => $user->blogCount(),
            'verified'  => $user->hasVerifiedEmail(),
            'domain'    => Url::mainDomain(),
        ]);
    }

    public function overview(string $blog): Response
    {
        $found = $this->openBlog($blog);
        if ($found instanceof Response) {
            return $found;
        }

        $all = Post::forDashboard($found);
        $drafts = 0;
        $scheduled = 0;
        foreach ($all as $post) {
            if ($post->isDraft()) {
                $drafts++;
            } elseif ($post->isScheduled()) {
                $scheduled++;
            }
        }

        return $this->panel('dashboard/blog-overview', [
            'pageTitle'   => $found->title,
            'section'     => 'overview',
            'blog'        => $found,
            'address'     => Url::blogRoot($found),
            'posts'       => count($all),
            'pages'       => max(0, $found->postCount(true) - count($all)),
            'drafts'      => $drafts,
            'scheduled'   => $scheduled,
            'recent'      => array_slice($all, 0, 8),
            'files'       => Media::countForBlog($found),
            'storage'     => Media::humanBytes($found->storage_used),
            'quota'       => Media::humanBytes($this->limit('storage_per_blog', 524288000)),
            'subscribers' => $found->subscriptions_active ? Subscriber::countForBlog($found) : null,
            'reads'       => $found->analytics_active ? Hit::totals($found, 7) : null,
            'readers'     => $found->analytics_active ? Hit::currentReaders($found, 5) : null,
            'lastPost'    => Dates::parse($found->last_posted_at),
        ]);
    }

    public function content(string $blog): Response
    {
        $found = $this->openBlog($blog);
        if ($found instanceof Response) {
            return $found;
        }

        if ($this->request->isPost()) {
            $csrf = $this->requireCsrf();
            if ($csrf !== null) {
                return $csrf;
            }

            $title = $this->request->trimmed('title');
            $content = $this->request->input('content', '') ?? '';
            $maxChars = $this->limit('post_max_chars', 1000000);

            if ($title === '') {
                return $this->withInput(
                    $this->blogUrl($found, '/contenuto'),
                    $this->request->post,
                    __('blog.create.title_required')
                );
            }
            if (mb_strlen($content) > $maxChars) {
                return $this->withInput(
                    $this->blogUrl($found, '/contenuto'),
                    $this->request->post,
                    __('post.error.too_long', ['max' => $maxChars])
                );
            }

            $found->update([
                'title'   => mb_substr($title, 0, 200),
                'content' => $content,
            ]);
            $found->refreshDodginess();

            return $this->succeed($this->blogUrl($found, '/contenuto'), __('blog.content.saved'));
        }

        return $this->panel('dashboard/blog-content', [
            'pageTitle' => __('blog.content.title'),
            'section'   => 'content',
            'blog'      => $found,
            'media'     => $this->mediaCatalogue($found),
            'labels'    => $this->editorLabels(),
        ]);
    }
}
