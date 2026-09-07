<?php

declare(strict_types=1);

namespace Noblogs\Core;

use Noblogs\Models\Blog;

/**
 * Costruzione degli URL, unico posto che conosce la differenza tra modalità
 * sottodominio, fallback su path e dominio personalizzato.
 */
final class Url
{
    private static ?Tenant $tenant = null;

    public static function useTenant(Tenant $tenant): void
    {
        self::$tenant = $tenant;
    }

    public static function scheme(): string
    {
        return Config::get('site.https', true) ? 'https' : 'http';
    }

    public static function mainDomain(): string
    {
        return (string) Config::get('site.domain', 'localhost');
    }

    /** URL assoluto su dominio principale (landing, login, dashboard). */
    public static function platform(string $path = '/'): string
    {
        return self::scheme() . '://' . self::mainDomain() . self::normalize($path);
    }

    /** URL relativo per una rotta di piattaforma; usa il dominio corrente. */
    public static function to(string $path = '/'): string
    {
        return self::normalize($path);
    }

    /**
     * Indirizzo canonico di un blog, quello che compare nei feed, nella
     * sitemap e nei tag canonical.
     */
    public static function blogRoot(Blog $blog): string
    {
        if ($blog->domain !== null && $blog->domain !== '' && Config::get('routing.custom_domains', true)) {
            return self::scheme() . '://' . $blog->domain;
        }
        if (Config::get('routing.mode') === 'path') {
            return self::platform('/' . $blog->subdomain);
        }
        return self::scheme() . '://' . $blog->subdomain . '.' . self::mainDomain();
    }

    /** URL assoluto di una risorsa dentro un blog. */
    public static function blog(Blog $blog, string $path = '/'): string
    {
        return rtrim(self::blogRoot($blog), '/') . self::normalize($path);
    }

    /**
     * URL relativo dentro il blog che stiamo servendo. Tiene conto del
     * prefisso quando il blog è raggiunto tramite fallback su path, così i
     * link nei template funzionano in entrambe le modalità.
     */
    public static function site(string $path = '/'): string
    {
        $base = self::$tenant?->basePath ?? '';
        $normalized = self::normalize($path);
        if ($base === '') {
            return $normalized;
        }
        return $normalized === '/' ? $base . '/' : $base . $normalized;
    }

    public static function post(Blog $blog, string $slug): string
    {
        return self::blog($blog, '/' . ltrim($slug, '/') . '/');
    }

    public static function asset(string $path): string
    {
        // Gli asset stanno sempre sul dominio principale: un solo posto da
        // mettere in cache anche quando i blog hanno domini propri.
        return self::platform('/assets/' . ltrim($path, '/'));
    }

    public static function media(Blog $blog, string $path): string
    {
        return self::platform('/media/' . $blog->subdomain . '/' . ltrim($path, '/'));
    }

    /** Aggiunge o sostituisce parametri nella query string di un URL. */
    public static function withQuery(string $url, array $params): string
    {
        $parts = parse_url($url);
        $existing = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $existing);
        }
        foreach ($params as $key => $value) {
            if ($value === null) {
                unset($existing[$key]);
            } else {
                $existing[$key] = $value;
            }
        }
        $query = http_build_query($existing);

        $base = ($parts['scheme'] ?? '') !== ''
            ? $parts['scheme'] . '://' . ($parts['host'] ?? '') . ($parts['path'] ?? '')
            : ($parts['path'] ?? '');

        return $base . ($query !== '' ? '?' . $query : '');
    }

    private static function normalize(string $path): string
    {
        if ($path === '' || $path === '/') {
            return '/';
        }
        return '/' . ltrim($path, '/');
    }
}
