<?php
/**
 * Tabella delle rotte.
 *
 * Restituisce una funzione che, dato il tenant risolto, costruisce il router
 * adatto: quello della piattaforma o quello di un blog. Le due tabelle sono
 * separate perché lo stesso percorso ha significati diversi nei due contesti —
 * su un blog, /dashboard/ è un possibile articolo, non il pannello.
 */

declare(strict_types=1);

use Noblogs\Controllers\Admin;
use Noblogs\Controllers\Auth;
use Noblogs\Controllers\Dashboard;
use Noblogs\Controllers\Platform;
use Noblogs\Controllers\Site;
use Noblogs\Core\Request;
use Noblogs\Core\Response;
use Noblogs\Core\Router;
use Noblogs\Core\Tenant;

return static function (Request $request, Tenant $tenant): Response {
    $router = new Router();

    if ($tenant->isBlog()) {
        registerSiteRoutes($router, $tenant);
    } else {
        registerPlatformRoutes($router);
    }

    return $router->dispatch($request, ['tenant' => $tenant]);
};

// ---------------------------------------------------------------------------
// Rotte di un blog ospite
// ---------------------------------------------------------------------------
function registerSiteRoutes(Router $router, Tenant $tenant): void
{
    $blog = $tenant->blog();

    $router->get('/', [Site\HomeController::class, 'index']);

    // L'elenco degli articoli sta sul percorso scelto dall'autore; /blog resta
    // sempre valido, così i link condivisi non si rompono se lo cambia.
    $listing = trim($blog->blog_path, '/') ?: 'blog';
    $router->get('/' . $listing, [Site\ArchiveController::class, 'index']);
    if ($listing !== 'blog') {
        $router->get('/blog', [Site\ArchiveController::class, 'index']);
    }

    $router->get('/cerca', [Site\SearchController::class, 'index']);

    // Feed: un solo controller dietro tutti i nomi con cui i lettori di feed
    // provano a indovinare l'indirizzo.
    foreach (['/feed', '/feed/atom', '/feed/rss', '/rss', '/atom',
              '/feed.xml', '/rss.xml', '/atom.xml', '/index.xml'] as $path) {
        $router->get($path, [Site\FeedController::class, 'index']);
    }
    if ($blog->rss_alias !== null && trim($blog->rss_alias) !== '') {
        $router->get('/' . trim($blog->rss_alias, '/'), [Site\FeedController::class, 'index']);
    }

    $router->get('/sitemap.xml', [Site\SitemapController::class, 'index']);
    $router->get('/robots.txt', [Site\RobotsController::class, 'index']);

    $router->any('/iscriviti', [Site\SubscribeController::class, 'index']);
    $router->get('/conferma-iscrizione', [Site\SubscribeController::class, 'confirm']);
    $router->get('/disiscriviti', [Site\SubscribeController::class, 'unsubscribe']);

    $router->post('/hit', [Site\AnalyticsController::class, 'record']);
    $router->get('/upvote', [Site\UpvoteController::class, 'info']);
    $router->post('/upvote', [Site\UpvoteController::class, 'toggle']);
    $router->get('/upvote-info/{uid}', [Site\UpvoteController::class, 'info']);

    // Jolly finale: qualsiasi altro percorso è lo slug di un post o di una
    // pagina, un alias da reindirizzare, oppure un 404 con il tema del blog.
    $router->get('/{path}', [Site\PostController::class, 'show']);
    $router->fallback(static function (Request $request, array $context): Response {
        $controller = new Site\PostController($request, $context['tenant']);
        return $controller->missing();
    });
}

// ---------------------------------------------------------------------------
// Rotte della piattaforma
// ---------------------------------------------------------------------------
function registerPlatformRoutes(Router $router): void
{
    // Pagine pubbliche
    $router->get('/', [Platform\HomeController::class, 'index']);
    $router->get('/esplora', [Platform\DiscoverController::class, 'index']);
    $router->get('/esplora/cerca', [Platform\DiscoverController::class, 'search']);
    $router->get('/esplora/feed', [Platform\DiscoverController::class, 'feed']);
    $router->get('/esplora/caso', [Platform\DiscoverController::class, 'random']);
    $router->get('/esplora/caso-blog', [Platform\DiscoverController::class, 'randomBlog']);
    $router->get('/informazioni', [Platform\PageController::class, 'about']);
    $router->get('/privacy', [Platform\PageController::class, 'privacy']);
    $router->get('/termini', [Platform\PageController::class, 'terms']);
    $router->get('/aiuto', [Platform\PageController::class, 'help']);
    $router->get('/aiuto/markdown', [Platform\PageController::class, 'markdown']);
    $router->get('/robots.txt', [Platform\PageController::class, 'robots']);
    $router->get('/sitemap.xml', [Platform\PageController::class, 'sitemap']);

    // Accesso
    $router->any('/accedi', [Auth\LoginController::class, 'login']);
    $router->post('/esci', [Auth\LoginController::class, 'logout']);
    $router->any('/registrati', [Auth\RegisterController::class, 'register']);
    $router->get('/verifica-email', [Auth\RegisterController::class, 'verify']);
    $router->post('/verifica-email/rinvia', [Auth\RegisterController::class, 'resend']);
    $router->any('/password/dimenticata', [Auth\PasswordController::class, 'request']);
    $router->any('/password/reimposta', [Auth\PasswordController::class, 'reset']);

    // Pannello dell'utente
    $router->get('/dashboard', [Dashboard\HomeController::class, 'index']);
    $router->any('/dashboard/nuovo-blog', [Dashboard\BlogController::class, 'create']);
    $router->any('/dashboard/account', [Dashboard\AccountController::class, 'edit']);
    $router->post('/dashboard/account/elimina', [Dashboard\AccountController::class, 'destroy']);

    // Pannello di un singolo blog
    $router->get('/dashboard/{blog}', [Dashboard\BlogController::class, 'overview']);
    $router->any('/dashboard/{blog}/contenuto', [Dashboard\BlogController::class, 'content']);
    $router->any('/dashboard/{blog}/impostazioni', [Dashboard\SettingsController::class, 'general']);
    $router->any('/dashboard/{blog}/impostazioni/avanzate', [Dashboard\SettingsController::class, 'advanced']);
    $router->any('/dashboard/{blog}/impostazioni/dominio', [Dashboard\SettingsController::class, 'domain']);
    $router->any('/dashboard/{blog}/impostazioni/redirect', [Dashboard\SettingsController::class, 'redirects']);
    $router->post('/dashboard/{blog}/elimina', [Dashboard\SettingsController::class, 'destroy']);
    $router->any('/dashboard/{blog}/aspetto', [Dashboard\ThemeController::class, 'edit']);
    $router->any('/dashboard/{blog}/navigazione', [Dashboard\ThemeController::class, 'nav']);

    $router->get('/dashboard/{blog}/articoli', [Dashboard\PostController::class, 'index']);
    $router->get('/dashboard/{blog}/pagine', [Dashboard\PostController::class, 'pages']);
    $router->any('/dashboard/{blog}/articoli/nuovo', [Dashboard\PostController::class, 'create']);
    $router->any('/dashboard/{blog}/articoli/{id}', [Dashboard\PostController::class, 'edit']);
    $router->post('/dashboard/{blog}/articoli/{id}/elimina', [Dashboard\PostController::class, 'destroy']);
    $router->post('/dashboard/{blog}/articoli/{id}/duplica', [Dashboard\PostController::class, 'duplicate']);
    $router->post('/dashboard/{blog}/anteprima', [Dashboard\PostController::class, 'preview']);

    $router->get('/dashboard/{blog}/statistiche', [Dashboard\AnalyticsController::class, 'index']);
    $router->any('/dashboard/{blog}/file', [Dashboard\MediaController::class, 'index']);
    $router->post('/dashboard/{blog}/file/{id}/elimina', [Dashboard\MediaController::class, 'destroy']);
    $router->any('/dashboard/{blog}/iscritti', [Dashboard\SubscriberController::class, 'index']);
    $router->get('/dashboard/{blog}/esporta', [Dashboard\ImportExportController::class, 'export']);
    $router->any('/dashboard/{blog}/importa', [Dashboard\ImportExportController::class, 'import']);

    // Amministrazione
    $router->get('/admin', [Admin\DashboardController::class, 'index']);
    $router->get('/admin/blog', [Admin\BlogController::class, 'index']);
    $router->post('/admin/blog/{id}/azione', [Admin\BlogController::class, 'action']);
    $router->get('/admin/utenti', [Admin\UserController::class, 'index']);
    $router->post('/admin/utenti/{id}/azione', [Admin\UserController::class, 'action']);
    $router->any('/admin/impostazioni', [Admin\SettingsController::class, 'edit']);
    $router->get('/admin/registro', [Admin\DashboardController::class, 'log']);
    $router->post('/admin/manutenzione', [Admin\DashboardController::class, 'maintenance']);

    $router->fallback(static function (Request $request, array $context): Response {
        $controller = new Platform\PageController($request, $context['tenant']);
        return $controller->missing();
    });
}
