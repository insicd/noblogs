<?php

declare(strict_types=1);

namespace Noblogs\Markdown;

/**
 * Intestazione dei file markdown.
 *
 * Accetta il blocco Jekyll / Hugo / Obsidian (tre trattini) e quello di
 * Bear Blog (metadati in testa, poi una riga di tre underscore):
 *
 *     ---
 *     titolo: Il mio post
 *     tag: appunti, lavoro
 *     ---
 *
 *     Testo del post.
 *
 * Nella dashboard i metadati si compilano in campi dedicati; questo formato
 * serve a importare ed esportare senza perdere nulla, e a chi preferisce
 * scrivere l'articolo intero in un file.
 *
 * Non è YAML: il parsing si ferma alla prima virgola, i valori restano stringhe
 * e le liste si scrivono separate da virgole. Un YAML vero cambierebbe il
 * significato di valori comuni come gli orari (12:30) e gli URL.
 */
final class Frontmatter
{
    /** Chiavi riconosciute, con i sinonimi italiani e inglesi. */
    private const ALIASES = [
        'titolo'           => 'title',
        'title'            => 'title',
        'link'             => 'slug',
        'slug'             => 'slug',
        'percorso'         => 'slug',
        'alias'            => 'alias',
        'data'             => 'published_at',
        'date'             => 'published_at',
        'published_date'   => 'published_at',
        'published_at'     => 'published_at',
        'pubblicato'       => 'is_published',
        'publish'          => 'is_published',
        'published'        => 'is_published',
        'bozza'            => 'draft',
        'draft'            => 'draft',
        'pagina'           => 'is_page',
        'is_page'          => 'is_page',
        'page'             => 'is_page',
        'tag'              => 'tags',
        'tags'             => 'tags',
        'descrizione'      => 'meta_description',
        'description'      => 'meta_description',
        'meta_description' => 'meta_description',
        'immagine'         => 'meta_image',
        'image'            => 'meta_image',
        'meta_image'       => 'meta_image',
        'canonical'        => 'canonical_url',
        'canonical_url'    => 'canonical_url',
        'lingua'           => 'lang',
        'lang'             => 'lang',
        'classe'           => 'class_name',
        'class'            => 'class_name',
        'class_name'       => 'class_name',
        'in_vetrina'       => 'make_discoverable',
        'discoverable'     => 'make_discoverable',
        'make_discoverable' => 'make_discoverable',
    ];

    /** Chiavi il cui valore è booleano. */
    private const BOOLEANS = ['is_published', 'is_page', 'draft', 'make_discoverable'];

    /**
     * Separa intestazione e corpo.
     *
     * @return array{0:array<string,string|bool|list<string>>,1:string,2:list<string>}
     *         Metadati, contenuto, avvisi sulle chiavi non riconosciute.
     */
    public static function parse(string $document): array
    {
        $document = str_replace(["\r\n", "\r"], "\n", $document);
        $document = preg_replace('/^\xEF\xBB\xBF/', '', $document) ?? $document;

        $split = self::splitHeader($document);
        if ($split === null) {
            return [[], ltrim($document, "\n"), []];
        }

        [$header, $body] = $split;
        $meta = [];
        $warnings = [];

        foreach (explode("\n", $header) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, ':')) {
                $warnings[] = __('frontmatter.warning.malformed', ['line' => $line]);
                continue;
            }

            [$key, $value] = explode(':', $line, 2);
            $key = mb_strtolower(trim($key));
            $value = self::unquote(trim($value));

            $canonical = self::ALIASES[$key] ?? null;
            if ($canonical === null) {
                $warnings[] = __('frontmatter.warning.unknown_key', ['key' => $key]);
                continue;
            }

            $meta[$canonical] = match (true) {
                in_array($canonical, self::BOOLEANS, true) => self::toBool($value),
                $canonical === 'tags'                      => \Noblogs\Support\Str::tags($value),
                default                                    => $value,
            };
        }

        // 'bozza: sì' è l'opposto di 'pubblicato: sì'.
        if (isset($meta['draft'])) {
            $meta['is_published'] = !$meta['draft'];
            unset($meta['draft']);
        }

        return [$meta, ltrim($body, "\n"), $warnings];
    }

    /**
     * Compone un documento markdown con la sua intestazione, per l'esportazione.
     *
     * @param array<string,string|bool|list<string>|null> $meta
     */
    public static function compose(array $meta, string $content): string
    {
        $lines = [];
        foreach ($meta as $key => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            $rendered = match (true) {
                is_bool($value)  => $value ? 'sì' : 'no',
                is_array($value) => implode(', ', $value),
                default          => (string) $value,
            };
            // Un valore che comincia o finisce con spazi, o che contiene un
            // ritorno a capo, va protetto dalle virgolette.
            if ($rendered !== trim($rendered) || str_contains($rendered, "\n")) {
                $rendered = '"' . str_replace(['"', "\n"], ['\"', ' '], $rendered) . '"';
            }
            $lines[] = $key . ': ' . $rendered;
        }

        if ($lines === []) {
            return $content;
        }

        return "---\n" . implode("\n", $lines) . "\n---\n\n" . ltrim($content, "\n");
    }

    /**
     * Separa intestazione e corpo, riconoscendo tre forme:
     *
     *   ---          Jekyll / Hugo / Obsidian
     *   titolo: …
     *   ---
     *
     *   titolo: …    Bear Blog: metadati in testa e una riga di tre underscore
     *   ___
     *
     *   ---          ibrida, vista negli export misti
     *   titolo: …
     *   ___
     *
     * @return array{0:string,1:string}|null
     */
    private static function splitHeader(string $document): ?array
    {
        if (preg_match('/^---[ \t]*\n(.*?)\n---[ \t]*(?:\n|$)/s', $document, $matches)) {
            return [$matches[1], substr($document, strlen($matches[0]))];
        }

        if (preg_match('/^(?:---[ \t]*\n)?(.*?)\n_{3,}[ \t]*(?:\n|$)/s', $document, $matches)) {
            if (self::looksLikeFrontmatter($matches[1])) {
                return [$matches[1], substr($document, strlen($matches[0]))];
            }
        }

        return null;
    }

    /**
     * Un blocco è un'intestazione solo se contiene almeno una chiave nota e
     * ogni riga non vuota è una coppia chiave: valore. Così un articolo che
     * parla di `___` nel primo paragrafo non viene mangiato per sbaglio.
     */
    private static function looksLikeFrontmatter(string $header): bool
    {
        $known = 0;
        $lines = 0;

        foreach (explode("\n", $header) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, ':')) {
                return false;
            }
            $lines++;
            $key = mb_strtolower(trim(explode(':', $line, 2)[0]));
            if (isset(self::ALIASES[$key])) {
                $known++;
            }
        }

        return $lines > 0 && $known > 0;
    }

    private static function unquote(string $value): string
    {
        if (mb_strlen($value) >= 2) {
            $first = mb_substr($value, 0, 1);
            $last = mb_substr($value, -1);
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                return mb_substr($value, 1, -1);
            }
        }
        return $value;
    }

    private static function toBool(string $value): bool
    {
        return in_array(mb_strtolower(trim($value)), ['1', 'true', 'sì', 'si', 'yes', 'vero', 'on'], true);
    }
}
