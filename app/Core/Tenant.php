<?php

declare(strict_types=1);

namespace Noblogs\Core;

use Noblogs\Models\Blog;

/**
 * Contesto della richiesta: stiamo servendo la piattaforma o un blog ospite?
 *
 * La risoluzione avviene una sola volta per richiesta, nel front controller, e
 * il risultato viene passato ai controller.
 */
final class Tenant
{
    public const VIA_PLATFORM = 'platform';
    public const VIA_SUBDOMAIN = 'subdomain';
    public const VIA_PATH = 'path';
    public const VIA_DOMAIN = 'domain';

    private function __construct(
        public readonly ?Blog $blog,
        public readonly string $via,
        /** Prefisso da anteporre agli URL interni del blog, '' oppure '/nome'. */
        public readonly string $basePath,
        /** Vero quando l'host richiesto non è quello canonico del blog. */
        public readonly bool $needsCanonicalRedirect = false,
    ) {
    }

    public static function platform(): self
    {
        return new self(null, self::VIA_PLATFORM, '');
    }

    public function isPlatform(): bool
    {
        return $this->blog === null;
    }

    public function isBlog(): bool
    {
        return $this->blog !== null;
    }

    /**
     * Il blog servito, garantito non nullo. Da usare nei controller di sito,
     * dove il router ha già verificato la presenza del tenant.
     */
    public function blog(): Blog
    {
        if ($this->blog === null) {
            throw new \LogicException('Nessun blog associato a questa richiesta.');
        }
        return $this->blog;
    }

    /**
     * Individua il blog a partire dalla richiesta.
     *
     * Ordine: host principale → sottodominio → dominio personalizzato. Se la
     * richiesta arriva sul dominio principale e il primo segmento del path
     * corrisponde a un blog, si applica il fallback su path (se abilitato).
     */
    public static function resolve(Request $request): self
    {
        $mainDomain = strtolower((string) Config::get('site.domain', 'localhost'));
        $extraHosts = array_map('strtolower', (array) Config::get('site.extra_hosts', []));
        $host = $request->host;

        $isMainHost = $host === $mainDomain
            || $host === 'www.' . $mainDomain
            || in_array($host, $extraHosts, true);

        if (!$isMainHost) {
            $suffix = '.' . $mainDomain;
            if (str_ends_with($host, $suffix)) {
                $subdomain = substr($host, 0, -strlen($suffix));
                // Solo il terzo livello: un eventuale quarto livello non è un blog.
                if ($subdomain !== '' && !str_contains($subdomain, '.')) {
                    $blog = Blog::findBySubdomain($subdomain);
                    if ($blog !== null && $blog->isServable()) {
                        return self::forBlog($blog, self::VIA_SUBDOMAIN, '', $host);
                    }
                }
                return self::platform();
            }

            if (Config::get('routing.custom_domains', true)) {
                $blog = Blog::findByDomain($host);
                if ($blog !== null && $blog->isServable()) {
                    return self::forBlog($blog, self::VIA_DOMAIN, '', $host);
                }
            }

            // Host sconosciuto: trattato come piattaforma, che risponderà 404.
            return self::platform();
        }

        if (Config::get('routing.path_fallback', true) || Config::get('routing.mode') === 'path') {
            $first = $request->segments[0] ?? '';
            if ($first !== '' && !self::isPlatformSegment($first)) {
                $blog = Blog::findBySubdomain($first);
                if ($blog !== null && $blog->isServable()) {
                    return self::forBlog($blog, self::VIA_PATH, '/' . $blog->subdomain, $host);
                }
            }
        }

        return self::platform();
    }

    /**
     * 301 verso l'host canonico del blog, se la richiesta è arrivata da un
     * altro nome. Succede quando il blog ha un dominio proprio e qualcuno
     * visita ancora il sottodominio (o il percorso di fallback).
     *
     * Il fallback su path, da solo, non reindirizza: è fatto apposta per
     * restare raggiungibile prima che il DNS dei sottodomini sia a posto.
     */
    public function canonicalRedirect(Request $request): ?Response
    {
        if (!$this->needsCanonicalRedirect || $this->blog === null || !$request->isGet()) {
            return null;
        }

        $stripped = $this->stripBasePath($request);
        $location = Url::blog($this->blog, $stripped->path);
        if ($request->query !== []) {
            $query = http_build_query($request->query);
            if ($query !== '') {
                $location .= '?' . $query;
            }
        }

        return Response::redirect($location, 301)->noIndex();
    }

    private static function forBlog(Blog $blog, string $via, string $basePath, string $host): self
    {
        $canonicalHost = self::canonicalHost($blog);

        return new self(
            $blog,
            $via,
            $basePath,
            $canonicalHost !== null && $host !== $canonicalHost
        );
    }

    /**
     * Host verso cui conviene reindirizzare. Vale solo se il blog ha un
     * dominio proprio: altrimenti sottodominio e percorso convivono.
     */
    private static function canonicalHost(Blog $blog): ?string
    {
        if (!Config::get('routing.custom_domains', true)) {
            return null;
        }
        $domain = strtolower(trim((string) $blog->domain));

        return $domain !== '' ? $domain : null;
    }

    /**
     * Segmenti riservati alla piattaforma: nessun blog può prevalere su
     * queste rotte, anche se qualcuno riuscisse a registrarne il nome.
     */
    private static function isPlatformSegment(string $segment): bool
    {
        static $reserved = [
            'accedi', 'esci', 'registrati', 'verifica-email', 'password',
            'dashboard', 'admin', 'esplora', 'informazioni', 'privacy',
            'termini', 'aiuto', 'assets', 'media', 'install', 'api',
            'sitemap.xml', 'robots.txt', 'favicon.ico',
        ];
        return in_array(strtolower($segment), $reserved, true);
    }

    /** Path del blog corrente ripulito dal prefisso del fallback su path. */
    public function stripBasePath(Request $request): Request
    {
        if ($this->basePath === '') {
            return $request;
        }
        $path = $request->path;
        if (str_starts_with($path, $this->basePath)) {
            $path = substr($path, strlen($this->basePath));
        }
        return $request->withPath($path === '' ? '/' : $path);
    }
}
