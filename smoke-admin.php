<?php
// Script temporaneo di collaudo dell'area di amministrazione. Da cancellare.
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use Noblogs\Controllers\Admin;
use Noblogs\Core\Database;
use Noblogs\Core\Request;
use Noblogs\Core\Session;
use Noblogs\Core\Tenant;
use Noblogs\Core\Url;
use Noblogs\Core\View;
use Noblogs\Models\Blog;
use Noblogs\Models\Post;
use Noblogs\Models\User;

View::setBasePath(__DIR__ . '/app/Views');
$db = Database::instance();
$tenant = Tenant::platform();
Url::useTenant($tenant);
View::share('tenant', $tenant);
View::share('siteName', 'Noblogs di prova');

// --- dati di prova ---------------------------------------------------------
$admin = User::findByEmail('terzo@blog.example');
$plain = User::findByEmail('utente@blog.example') ?? User::create('utente@blog.example', 'passwordlunga1');
$mod = User::findByEmail('mod@blog.example') ?? User::create('mod@blog.example', 'passwordlunga1', 'moderator');

$hasBlog = static fn(string $sub): bool => (int) $db->fetchColumn('SELECT COUNT(*) FROM {{blogs}} WHERE subdomain = :s', ['s' => $sub]) > 0;

if (!$hasBlog('provablog')) {
    $blog = Blog::create($plain, 'provablog', 'Blog di prova', "# Ciao\n\nContenuto sospetto di prova con parecchie parole per l'estratto.");
    $blog->update(['dodginess_score' => 2.5, 'to_review' => true, 'storage_used' => 12345]);
    $post = Post::make($blog);
    $post->fill(['title' => 'Primo articolo', 'slug' => 'primo', 'content' => 'testo']);
    $post->save();
}
if (!$hasBlog('altroblog')) {
    $b2 = Blog::create($plain, 'altroblog', 'Altro blog', 'contenuto');
    $b2->update(['reviewed' => true, 'flagged' => true, 'allow_raw_html' => true]);
}

// --- utilità ---------------------------------------------------------------
Session::start();

function run(string $label, callable $fn): void
{
    try {
        $response = $fn();
        $body = $response->body();
        $status = $response->status();
        $flag = $status === 200 ? 'OK ' : ($status === 404 ? '404' : (string) $status);
        printf("%-46s %s  %6d byte\n", $label, $flag, strlen($body));
        if ($status === 200 && !str_contains($body, '</html>') && $body !== '') {
            echo "   !! HTML incompleto\n";
        }
    } catch (\Throwable $e) {
        printf("%-46s ECCEZIONE %s\n   %s (%s:%d)\n", $label, $e::class, $e->getMessage(), $e->getFile(), $e->getLine());
    }
}

function req(string $method, string $path, array $post = [], array $query = []): Request
{
    return new Request($method, '127.0.0.1', $path, false, $query, $post);
}

$id = static fn(string $sub): int => (int) $db->fetchColumn('SELECT id FROM {{blogs}} WHERE subdomain = :s', ['s' => $sub]);
$blog = Blog::find($id('provablog'));
$flagged = Blog::find($id('altroblog'));

// --- 1) senza permessi: deve essere 404 ------------------------------------
Session::forget('_user_id');
(function () { $r = new ReflectionClass(\Noblogs\Core\Auth::class); foreach (['cached', 'resolved'] as $p) { $prop = $r->getProperty($p); $prop->setValue(null, $p === 'cached' ? null : false); } })();

run('anonimo -> /admin (atteso 404)', fn() => (new Admin\DashboardController(req('GET', '/admin'), $tenant))->index());

Session::put('_user_id', $plain->id);
(function () { $r = new ReflectionClass(\Noblogs\Core\Auth::class); foreach (['cached', 'resolved'] as $p) { $prop = $r->getProperty($p); $prop->setValue(null, $p === 'cached' ? null : false); } })();
run('utente normale -> /admin (atteso 404)', fn() => (new Admin\DashboardController(req('GET', '/admin'), $tenant))->index());

run('moderatore? no: impostazioni (atteso 404)', function () use ($tenant) {
    return (new Admin\SettingsController(req('GET', '/admin/impostazioni'), $tenant))->edit();
});

// --- 2) come moderatore ----------------------------------------------------
Session::put('_user_id', $mod->id);
(function () { $r = new ReflectionClass(\Noblogs\Core\Auth::class); foreach (['cached', 'resolved'] as $p) { $prop = $r->getProperty($p); $prop->setValue(null, $p === 'cached' ? null : false); } })();

run('moderatore -> impostazioni (atteso 404)', fn() => (new Admin\SettingsController(req('GET', '/admin/impostazioni'), $tenant))->edit());
run('moderatore -> dashboard', fn() => (new Admin\DashboardController(req('GET', '/admin'), $tenant))->index());
run('moderatore -> elimina utente (atteso 404)', function () use ($tenant, $plain) {
    $r = req('POST', '/admin/utenti/x/azione', ['azione' => 'elimina', '_token' => \Noblogs\Core\Csrf::token()]);
    return (new Admin\UserController($r, $tenant))->action((string) $plain->id);
});

// --- 3) come amministratore ------------------------------------------------
Session::put('_user_id', $admin->id);
(function () { $r = new ReflectionClass(\Noblogs\Core\Auth::class); foreach (['cached', 'resolved'] as $p) { $prop = $r->getProperty($p); $prop->setValue(null, $p === 'cached' ? null : false); } })();
$csrf = \Noblogs\Core\Csrf::token();

run('admin -> dashboard', fn() => (new Admin\DashboardController(req('GET', '/admin'), $tenant))->index());
run('admin -> registro', fn() => (new Admin\DashboardController(req('GET', '/admin/registro'), $tenant))->log());
run('admin -> blog', fn() => (new Admin\BlogController(req('GET', '/admin/blog'), $tenant))->index());
run('admin -> blog?stato=attesa&ordina=rischio', fn() => (new Admin\BlogController(req('GET', '/admin/blog', [], ['stato' => 'attesa', 'ordina' => 'rischio', 'q' => 'prova']), $tenant))->index());
run('admin -> blog?stato=segnalati', fn() => (new Admin\BlogController(req('GET', '/admin/blog', [], ['stato' => 'segnalati']), $tenant))->index());
run('admin -> utenti', fn() => (new Admin\UserController(req('GET', '/admin/utenti'), $tenant))->index());
run('admin -> utenti?stato=staff&ordina=blog', fn() => (new Admin\UserController(req('GET', '/admin/utenti', [], ['stato' => 'staff', 'ordina' => 'blog']), $tenant))->index());
run('admin -> impostazioni (form)', fn() => (new Admin\SettingsController(req('GET', '/admin/impostazioni'), $tenant))->edit());

run('admin -> POST manutenzione', fn() => (new Admin\DashboardController(req('POST', '/admin/manutenzione', ['_token' => $csrf]), $tenant))->maintenance());
run('admin -> POST manutenzione senza CSRF', fn() => (new Admin\DashboardController(req('POST', '/admin/manutenzione', []), $tenant))->maintenance());

run('admin -> approva blog', function () use ($tenant, $blog, $csrf) {
    return (new Admin\BlogController(req('POST', '/x', ['_token' => $csrf, 'azione' => 'approva', 'nota' => 'ok']), $tenant))->action((string) $blog->id);
});
run('admin -> azione inesistente', function () use ($tenant, $blog, $csrf) {
    return (new Admin\BlogController(req('POST', '/x', ['_token' => $csrf, 'azione' => 'boh']), $tenant))->action((string) $blog->id);
});
run('admin -> conferma eliminazione blog', function () use ($tenant, $flagged, $csrf) {
    return (new Admin\BlogController(req('POST', '/x', ['_token' => $csrf, 'azione' => 'elimina']), $tenant))->action((string) $flagged->id);
});
run('admin -> eliminazione blog con conferma errata', function () use ($tenant, $flagged, $csrf) {
    return (new Admin\BlogController(req('POST', '/x', ['_token' => $csrf, 'azione' => 'elimina', 'conferma' => 'sbagliato']), $tenant))->action((string) $flagged->id);
});
run('admin -> conferma eliminazione utente', function () use ($tenant, $plain, $csrf) {
    return (new Admin\UserController(req('POST', '/x', ['_token' => $csrf, 'azione' => 'elimina']), $tenant))->action((string) $plain->id);
});
run('admin -> auto-degradazione (bloccata)', function () use ($tenant, $admin, $csrf) {
    return (new Admin\UserController(req('POST', '/x', ['_token' => $csrf, 'azione' => 'revoca-ruolo']), $tenant))->action((string) $admin->id);
});
run('admin -> auto-eliminazione (bloccata)', function () use ($tenant, $admin, $csrf) {
    return (new Admin\UserController(req('POST', '/x', ['_token' => $csrf, 'azione' => 'elimina']), $tenant))->action((string) $admin->id);
});
run('admin -> cambia limite blog', function () use ($tenant, $plain, $csrf) {
    return (new Admin\UserController(req('POST', '/x', ['_token' => $csrf, 'azione' => 'cambia-limite-blog', 'limite' => '7']), $tenant))->action((string) $plain->id);
});
run('admin -> limite non valido', function () use ($tenant, $plain, $csrf) {
    return (new Admin\UserController(req('POST', '/x', ['_token' => $csrf, 'azione' => 'cambia-limite-blog', 'limite' => '9999']), $tenant))->action((string) $plain->id);
});
run('admin -> promuovi moderatore', function () use ($tenant, $plain, $csrf) {
    return (new Admin\UserController(req('POST', '/x', ['_token' => $csrf, 'azione' => 'promuovi-moderatore']), $tenant))->action((string) $plain->id);
});
run('admin -> sospendi utente', function () use ($tenant, $plain, $csrf) {
    return (new Admin\UserController(req('POST', '/x', ['_token' => $csrf, 'azione' => 'sospendi']), $tenant))->action((string) $plain->id);
});
run('admin -> salva impostazioni', function () use ($tenant, $csrf) {
    return (new Admin\SettingsController(req('POST', '/admin/impostazioni', [
        '_token' => $csrf, 'site_name' => 'Prova', 'tagline' => 'motto', 'contact_email' => 'a@b.it',
        'verify_email' => '1', 'review_blogs' => '1', 'registration_open' => '1',
        'blogs_per_user' => '5', 'posts_per_blog' => '100', 'storage_per_blog' => '200', 'upload_max' => '8',
        'notice' => 'Avviso di prova',
    ]), $tenant))->edit();
});
run('admin -> impostazioni non valide', function () use ($tenant, $csrf) {
    return (new Admin\SettingsController(req('POST', '/admin/impostazioni', [
        '_token' => $csrf, 'site_name' => '', 'blogs_per_user' => '5', 'posts_per_blog' => '100',
        'storage_per_blog' => '200', 'upload_max' => '8',
    ]), $tenant))->edit();
});
run('admin -> dashboard con avviso globale', fn() => (new Admin\DashboardController(req('GET', '/admin'), $tenant))->index());

echo "\n--- stato finale ---\n";
foreach ($db->fetchAll('SELECT email, role, is_active, max_blogs FROM {{users}} ORDER BY id') as $row) {
    printf("  %-24s %-10s attivo=%d limite=%d\n", $row['email'], $row['role'], (int) $row['is_active'], (int) $row['max_blogs']);
}
foreach ($db->fetchAll('SELECT subdomain, reviewed, to_review, hidden, flagged FROM {{blogs}}') as $row) {
    printf("  blog %-14s reviewed=%d to_review=%d hidden=%d flagged=%d\n", $row['subdomain'], (int) $row['reviewed'], (int) $row['to_review'], (int) $row['hidden'], (int) $row['flagged']);
}
echo "  impostazioni: " . json_encode(\Noblogs\Models\Setting::all(), JSON_UNESCAPED_UNICODE) . "\n";
echo "  registro: " . count(\Noblogs\Models\ModerationLog::recent(50)) . " righe\n";
