<?php

declare(strict_types=1);

namespace Noblogs\Controllers\Dashboard;

use Noblogs\Core\Response;
use Noblogs\Support\Dates;
use Noblogs\Models\Hit;

/**
 * Prima pagina del pannello: l'elenco dei blog dell'utente.
 */
final class HomeController extends DashboardController
{
    public function index(): Response
    {
        $guard = $this->requireAuth();
        if ($guard !== null) {
            return $guard;
        }

        $user = $this->account();
        $blogs = [];

        foreach ($user->blogs() as $blog) {
            $withPages = $blog->postCount(true);
            $posts = $blog->postCount();
            // Le statistiche si mostrano solo se il blog le raccoglie: un
            // «0 letture» su un blog senza analytics sarebbe una bugia.
            $reads = $blog->analytics_active ? Hit::totals($blog, 7) : null;

            $blogs[] = [
                'blog'     => $blog,
                'posts'    => $posts,
                'pages'    => max(0, $withPages - $posts),
                'reads'    => $reads['reads'] ?? null,
                'visitors' => $reads['visitors'] ?? null,
                'updated'  => Dates::parse($blog->updated_at),
            ];
        }

        return $this->panel('dashboard/home', [
            'pageTitle'   => __('dashboard.title'),
            'section'     => 'home',
            'blogs'       => $blogs,
            'canCreate'   => $user->canCreateBlog(),
            'maxBlogs'    => $user->max_blogs,
            'blogCount'   => count($blogs),
        ]);
    }
}
