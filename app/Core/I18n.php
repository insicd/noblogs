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
