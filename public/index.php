<?php
/**
 * Front controller: tutte le richieste passano di qui.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use Noblogs\Core\Config;
use Noblogs\Core\Database;
use Noblogs\Core\ErrorHandler;
use Noblogs\Core\I18n;
use Noblogs\Core\Request;
use Noblogs\Core\Response;
use Noblogs\Core\Session;
use Noblogs\Core\Tenant;
use Noblogs\Core\Url;
use Noblogs\Core\View;
use Noblogs\Models\Blog;

View::setBasePath(NOBLOGS_APP . '/Views');

$request = Request::capture();

// -----------------------------------------------------------------------
// Installazione non ancora eseguita: si dirotta tutto sull'installer.
// -----------------------------------------------------------------------
if (!Config::isInstalled()) {
    if (!str_starts_with($request->path, '/install')) {
        Response::redirect('/install/')->send();
        exit;
    }
    require NOBLOGS_ROOT . '/install/index.php';
    exit;
}

try {
    Database::instance();
    Blog::ensureRoutingColumn();
} catch (\Throwable $e) {
    ErrorHandler::log($e);
    Response::html(View::make('errors/database', ['debug' => Config::get('debug', false), 'error' => $e]), 503)
        ->withHeader('Retry-After', '120')
        ->send();
    exit;
}

$tenant = Tenant::resolve($request);
Url::useTenant($tenant);

if ($redirect = $tenant->canonicalRedirect($request)) {
    $redirect->send();
    exit;
}

// La sessione serve solo alla piattaforma: sui blog pubblici non si apre,
// così i lettori non ricevono nessun cookie.
if ($tenant->isPlatform()) {
    Session::start();
    if ($switch = I18n::consumeSwitch($request)) {
        $switch->send();
        exit;
    }
    I18n::load(I18n::fromRequest($request));
}

View::share('tenant', $tenant);
View::share('request', $request);
View::share('siteName', Config::get('site.name', 'Noblogs'));

$router = require NOBLOGS_APP . '/routes.php';

try {
    $routedRequest = $tenant->stripBasePath($request);
    $response = $router($routedRequest, $tenant);
} catch (\Throwable $e) {
    ErrorHandler::log($e);
    if (Config::get('debug', false)) {
        throw $e;
    }
    $response = Response::html(View::make('errors/500', ['tenant' => $tenant]), 500);
}

$response->send($request->method === 'HEAD');
