<?php

declare(strict_types=1);

namespace Noblogs\Support;

/**
 * Utilità sulle stringhe.
 */
final class Str
{
    /**
     * Slug URL-safe. Le lettere accentate vengono traslitterate quando
     * l'estensione intl o iconv è disponibile, altrimenti si ricade su una
     * mappa manuale che copre le lingue europee più comuni.
     */
    public static function slug(string $value, string $separator = '-', bool $allowSlashes = false): string
    {
        $value = self::transliterate($value);
        $value = mb_strtolower($value, 'UTF-8');

        $keep = $allowSlashes ? 'a-z0-9\/_' : 'a-z0-9_';
        $value = (string) preg_replace('/[^' . $keep . ']+/u', $separator, $value);
        $value = (string) preg_replace('/' . preg_quote($separator, '/') . '+/', $separator, $value);

        if ($allowSlashes) {
            $value = (string) preg_replace('#/+#', '/', $value);
            $value = (string) preg_replace('#' . preg_quote($separator, '#') . '?/' . preg_quote($separator, '#') . '?#', '/', $value);
        }

        return trim($value, $separator . '/');
    }

    public static function transliterate(string $value): string
    {
        if (class_exists(\Transliterator::class)) {
            $transliterator = \Transliterator::create('Any-Latin; Latin-ASCII');
            if ($transliterator !== null) {
                $converted = $transliterator->transliterate($value);
                if (is_string($converted)) {
                    return $converted;
                }
            }
        }

        static $map = [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'ae',
            'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i',
            'î' => 'i', 'ï' => 'i', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ö' => 'o', 'ø' => 'o', 'œ' => 'oe', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ý' => 'y', 'ÿ' => 'y', 'ß' => 'ss', 'ð' => 'd', 'þ' => 'th',
            'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n', 'ś' => 's', 'ź' => 'z',
            'ż' => 'z', 'č' => 'c', 'ď' => 'd', 'ě' => 'e', 'ň' => 'n', 'ř' => 'r', 'š' => 's',
            'ť' => 't', 'ů' => 'u', 'ž' => 'z', 'ğ' => 'g', 'ı' => 'i', 'ş' => 's',
            'α' => 'a', 'β' => 'b', 'γ' => 'g', 'δ' => 'd', 'ε' => 'e', 'θ' => 'th', 'λ' => 'l',
            'μ' => 'm', 'π' => 'p', 'σ' => 's', 'φ' => 'f', 'ω' => 'o',
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ж' => 'zh',
            'з' => 'z', 'и' => 'i', 'й' => 'i', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f',
            'х' => 'h', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ы' => 'y',
            'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        ];

        return strtr(mb_strtolower($value, 'UTF-8'), $map);
    }

    /** Identificatore casuale leggibile, senza caratteri ambigui. */
    public static function uid(int $length = 20): string
    {
        $alphabet = 'abcdefghijkmnpqrstuvwxyz23456789';
        $max = strlen($alphabet) - 1;
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }
        return $out;
    }

    public static function token(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    public static function limit(string $value, int $length, string $suffix = '…'): string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));
        if (mb_strlen($value, 'UTF-8') <= $length) {
            return $value;
        }
        $cut = mb_substr($value, 0, $length, 'UTF-8');
        $lastSpace = mb_strrpos($cut, ' ', 0, 'UTF-8');
        if ($lastSpace !== false && $lastSpace > $length * 0.6) {
            $cut = mb_substr($cut, 0, $lastSpace, 'UTF-8');
        }
        return rtrim($cut, " \t\n\r,.;:") . $suffix;
    }

    /** Testo semplice ricavato da markdown/HTML, per le meta description. */
    public static function plain(string $markdown): string
    {
        $text = strip_tags($markdown);
        // Rimuove blocchi di codice, direttive e la sintassi markdown residua.
        $text = (string) preg_replace('/```.*?```/s', ' ', $text);
        $text = (string) preg_replace('/\{\{.*?\}\}/s', ' ', $text);
        $text = (string) preg_replace('/!\[[^\]]*\]\([^)]*\)/', ' ', $text);
        $text = (string) preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $text);
        $text = (string) preg_replace('/^[>#\-\*\+\s]+/m', '', $text);
        $text = str_replace(['`', '*', '_', '~', '|'], '', $text);
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /** @return list<string> */
    public static function tags(string $value): array
    {
        $tags = [];
        foreach (preg_split('/[,\n]/', $value) ?: [] as $tag) {
            $tag = trim($tag);
            if ($tag === '' || mb_strlen($tag) > 50) {
                continue;
            }
            $key = mb_strtolower($tag, 'UTF-8');
            $tags[$key] = $tag;
        }
        return array_values(array_slice($tags, 0, 25));
    }

    public static function isEmail(string $value): bool
    {
        return (bool) filter_var($value, FILTER_VALIDATE_EMAIL) && mb_strlen($value) <= 191;
    }

    public static function isHttpUrl(string $value): bool
    {
        return (bool) filter_var($value, FILTER_VALIDATE_URL)
            && (bool) preg_match('#^https?://#i', $value);
    }

    /** Un solo emoji, usato per riconoscere le favicon testuali. */
    public static function isSingleGlyph(string $value): bool
    {
        $value = trim($value);
        return $value !== '' && mb_strlen($value, 'UTF-8') <= 4 && !str_contains($value, '/');
    }
}
