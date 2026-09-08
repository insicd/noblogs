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
     * Indirizzo pubblico di un blog: dominio proprio se c'è, altrimenti il
     * percorso sul dominio principale finché il terzo livello non esiste
     * ancora, e il sottodominio dopo l'approvazione.
     */
    public static function blogRoot(Blog $blog): string
    {
        if (self::customDomain($blog) !== null) {
            return self::scheme() . '://' . self::customDomain($blog);
        }
        if (Config::get('routing.mode') === 'path' || self::pathUntilReview($blog)) {
            return self::pathRoot($blog);
        }
        return self::subdomainRoot($blog);
    }

    /** Indirizzo via percorso: sempre https://dominio/nome. */
    public static function pathRoot(Blog $blog): string
    {
        return self::platform('/' . $blog->subdomain);
    }

    /** Indirizzo via terzo livello, o dominio proprio se è configurato. */
    public static function subdomainRoot(Blog $blog): string
    {
        if (self::customDomain($blog) !== null) {
            return self::scheme() . '://' . self::customDomain($blog);
        }
        return self::scheme() . '://' . $blog->subdomain . '.' . self::mainDomain();
    }

    /**
     * Il terzo livello non è ancora stato creato: l'unico indirizzo che
     * risponde è il percorso sul dominio principale.
     */
    public static function pathUntilReview(Blog $blog): bool
    {
        return !$blog->reviewed
            && Config::get('routing.mode') !== 'path'
            && Config::get('routing.path_fallback', true);
    }

    /** Sottodominio e percorso convivono su questa installazione. */
    public static function pathFallbackActive(): bool
    {
        return Config::get('routing.mode') !== 'path'
            && Config::get('routing.path_fallback', true);
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
        return self::localizePath(self::normalize($path));
    }

    /**
     * Riscrive gli href/src radice-relativi nel HTML di un blog servito via
     * percorso, così [Home](/) e i link scritti a mano funzionano anche su
     * dominio/nome e non solo sul terzo livello.
     */
    public static function localizeHtml(string $html): string
    {
        if ((self::$tenant?->basePath ?? '') === '') {
            return $html;
        }

        $html = preg_replace_callback(
            '/\b(href|src|action|poster|cite)=(["\'])([^"\']*)\2/i',
            static function (array $m): string {
                return $m[1] . '=' . $m[2] . self::localizePath($m[3]) . $m[2];
            },
            $html
        ) ?? $html;

        return preg_replace_callback(
            '/\bsrcset=(["\'])([^"\']*)\1/i',
            static function (array $m): string {
                $parts = [];
                foreach (explode(',', $m[2]) as $candidate) {
                    $candidate = trim($candidate);
                    if ($candidate === '') {
                        continue;
                    }
                    $bits = preg_split('/\s+/', $candidate, 2);
                    if ($bits === false) {
                        $parts[] = $candidate;
                        continue;
                    }
                    $bits[0] = self::localizePath($bits[0]);
                    $parts[] = implode(' ', $bits);
                }
                return 'srcset=' . $m[1] . implode(', ', $parts) . $m[1];
            },
            $html
        ) ?? $html;
    }

    /**
     * Rende assoluti gli URL radice-relativi, dopo averli localizzati al
     * tenant corrente. Serve ai feed: un aggregatore non ha un dominio di
     * origine su cui risolvere /articolo/.
     */
    public static function absolutizeHtml(string $html): string
    {
        $html = self::localizeHtml($html);
        $origin = rtrim(self::currentOrigin(), '/');

        $rewrite = static function (string $url) use ($origin): string {
            if ($url === '' || str_starts_with($url, '#') || str_starts_with($url, '//')) {
                return $url;
            }
            if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $url) === 1) {
                return $url;
            }
            if (str_starts_with($url, '/')) {
                return $origin . $url;
            }
            return $url;
        };

        $html = preg_replace_callback(
            '/\b(href|src|action|poster|cite)=(["\'])([^"\']*)\2/i',
            static fn(array $m): string => $m[1] . '=' . $m[2] . $rewrite($m[3]) . $m[2],
            $html
        ) ?? $html;

        return preg_replace_callback(
            '/\bsrcset=(["\'])([^"\']*)\1/i',
            static function (array $m) use ($rewrite): string {
                $parts = [];
                foreach (explode(',', $m[2]) as $candidate) {
                    $candidate = trim($candidate);
                    if ($candidate === '') {
                        continue;
                    }
                    $bits = preg_split('/\s+/', $candidate, 2);
                    if ($bits === false) {
                        $parts[] = $candidate;
                        continue;
                    }
                    $bits[0] = $rewrite($bits[0]);
                    $parts[] = implode(' ', $bits);
                }
                return 'srcset=' . $m[1] . implode(', ', $parts) . $m[1];
            },
            $html
        ) ?? $html;
    }

    /**
     * Prefissa un percorso radice-relativo con il basePath del tenant, lasciando
     * intatti gli URL già prefissati e quelli della piattaforma (/assets, /media).
     */
    public static function localizePath(string $url): string
    {
        $base = self::$tenant?->basePath ?? '';
        if ($base === '' || $url === '' || $url[0] !== '/' || str_starts_with($url, '//')) {
            return $url;
        }

        $path = $url;
        $query = '';
        $fragment = '';
        if (($hash = strpos($path, '#')) !== false) {
            $fragment = substr($path, $hash);
            $path = substr($path, 0, $hash);
        }
        if (($question = strpos($path, '?')) !== false) {
            $query = substr($path, $question);
            $path = substr($path, 0, $question);
        }

        if ($path === $base || str_starts_with($path, $base . '/')) {
            return $path . $query . $fragment;
        }

        $first = strtolower(explode('/', ltrim($path, '/'), 2)[0] ?? '');
        if ($first !== '' && Tenant::isReservedSegment($first)) {
            return $path . $query . $fragment;
        }

        $prefixed = $path === '/' ? $base . '/' : $base . $path;
        return $prefixed . $query . $fragment;
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

    private static function customDomain(Blog $blog): ?string
    {
        if (!Config::get('routing.custom_domains', true)) {
            return null;
        }
        $domain = strtolower(trim((string) $blog->domain));
        return $domain !== '' ? $domain : null;
    }

    /** Schema + host della richiesta corrente, senza percorso. */
    private static function currentOrigin(): string
    {
        $tenant = self::$tenant;
        if ($tenant !== null && $tenant->isBlog()) {
            $blog = $tenant->blog();
            return match ($tenant->via) {
                Tenant::VIA_DOMAIN => self::scheme() . '://' . $blog->domain,
                Tenant::VIA_SUBDOMAIN => self::scheme() . '://' . $blog->subdomain . '.' . self::mainDomain(),
                default => self::scheme() . '://' . self::mainDomain(),
            };
        }
        return self::scheme() . '://' . self::mainDomain();
    }
}
