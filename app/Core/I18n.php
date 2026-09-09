<?php

declare(strict_types=1);

namespace Noblogs\Core;

/**
 * Traduzioni. I file di lingua stanno in lang/<codice>.php e restituiscono un
 * array chiave => testo. Le chiavi mancanti ricadono sull'italiano e, in ultima
 * istanza, sulla chiave stessa: un'interfaccia parzialmente tradotta resta
 * comunque usabile.
 */
final class I18n
{
    public const COOKIE = 'nb_lang';

    private const FALLBACK = 'it';

    private static string $locale = self::FALLBACK;

    /** @var array<string,array<string,string>> */
    private static array $catalogues = [];

    public static function load(string $locale): void
    {
        self::$locale = self::isAvailable($locale) ? $locale : self::FALLBACK;
        self::catalogue(self::$locale);
        self::catalogue(self::FALLBACK);
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    /** @return list<string> */
    public static function available(): array
    {
        $locales = [];
        foreach (glob(NOBLOGS_ROOT . '/lang/*', GLOB_ONLYDIR) ?: [] as $directory) {
            $locales[] = basename($directory);
        }
        sort($locales);
        return $locales;
    }

    public static function isAvailable(string $locale): bool
    {
        return (bool) preg_match('/^[a-z]{2}(-[A-Za-z]{2})?$/', $locale)
            && is_dir(NOBLOGS_ROOT . '/lang/' . $locale);
    }

    /**
     * Lingua della piattaforma pubblica: scelta manuale, altrimenti italiano
     * se il browser è in italiano, inglese in ogni altro caso.
     */
    public static function fromRequest(Request $request): string
    {
        $cookie = strtolower(trim((string) $request->cookie(self::COOKIE, '')));
        if (self::isUiLocale($cookie)) {
            return $cookie;
        }

        return self::fromAcceptLanguage((string) ($request->header('Accept-Language') ?? ''));
    }

    /**
     * Se la richiesta porta ?lang=it|en, fissa la scelta in un cookie e
     * reindirizza via il parametro, così l'URL resta pulito.
     */
    public static function consumeSwitch(Request $request): ?Response
    {
        if (!$request->isGet()) {
            return null;
        }

        $chosen = strtolower(trim((string) $request->query('lang')));
        if (!self::isUiLocale($chosen)) {
            return null;
        }

        $query = $request->query;
        unset($query['lang']);
        $location = $request->path === '' ? '/' : $request->path;
        if ($query !== []) {
            $location .= '?' . http_build_query($query);
        }

        return Response::redirect($location, 302)
            ->withCookie(self::COOKIE, $chosen, [
                'expires'  => time() + 365 * 86400,
                'path'     => '/',
                'secure'   => (bool) Config::get('site.https', true),
                'httponly' => true,
                'samesite' => 'Lax',
            ])
            ->noIndex();
    }

    /** Collegamento per passare a una lingua, restando sulla pagina corrente. */
    public static function switchHref(Request $request, string $locale): string
    {
        $query = $request->query;
        $query['lang'] = $locale;
        $path = $request->path === '' ? '/' : $request->path;

        return $path . '?' . http_build_query($query);
    }

    /** @return list<string> */
    public static function uiLocales(): array
    {
        return ['it', 'en'];
    }

    public static function isUiLocale(string $locale): bool
    {
        return in_array($locale, self::uiLocales(), true);
    }

    /**
     * Prima lingua dichiarata dal browser: `it` se è italiano, altrimenti
     * `en`. Senza intestazione (crawler, curl) si resta sul fallback italiano.
     */
    public static function fromAcceptLanguage(string $header): string
    {
        foreach (explode(',', $header) as $part) {
            $part = trim($part);
            if ($part === '' || $part === '*') {
                continue;
            }
            $tag = strtolower(trim(explode(';', $part, 2)[0]));
            $primary = explode('-', $tag, 2)[0];
            if ($primary === 'it') {
                return 'it';
            }
            if (preg_match('/^[a-z]{2}$/', $primary) === 1) {
                return 'en';
            }
        }

        return self::FALLBACK;
    }

    /** @param array<string,string|int> $replacements */
    public static function translate(string $key, array $replacements = []): string
    {
        $text = self::catalogue(self::$locale)[$key]
            ?? self::catalogue(self::FALLBACK)[$key]
            ?? $key;

        foreach ($replacements as $name => $value) {
            $text = str_replace(':' . $name, (string) $value, $text);
        }
        return $text;
    }

    /**
     * Le traduzioni di una lingua sono divise per area in lang/<lingua>/*.php,
     * e qui vengono unite in un unico catalogo piatto. Ogni file restituisce
     * un array chiave => testo; le chiavi sono già prefissate per area, quindi
     * l'unione non produce collisioni.
     *
     * @return array<string,string>
     */
    private static function catalogue(string $locale): array
    {
        if (isset(self::$catalogues[$locale])) {
            return self::$catalogues[$locale];
        }

        $messages = [];
        foreach (glob(NOBLOGS_ROOT . '/lang/' . $locale . '/*.php') ?: [] as $file) {
            $data = require $file;
            if (is_array($data)) {
                $messages += $data;
            }
        }

        return self::$catalogues[$locale] = $messages;
    }
}
