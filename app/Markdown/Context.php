<?php

declare(strict_types=1);

namespace Noblogs\Markdown;

/**
 * Stato condiviso fra il parser di blocchi e quello inline: riferimenti di
 * link, note a piè di pagina, indice dei titoli e segnaposto opachi.
 *
 * I segnaposto servono a mettere al riparo tutto ciò che non deve subire
 * ulteriori elaborazioni (codice, HTML grezzo, direttive `{{ }}`): il
 * contenuto viene sostituito con un token di controllo e reinserito solo
 * alla fine, quando nessun'altra fase può più toccarlo.
 */
final class Context
{
    private const OPEN = "\x02";
    private const CLOSE = "\x03";

    /** @var list<string> */
    private array $protected = [];

    /** @var array<string, array{url: string, title: string}> */
    private array $references = [];

    /** @var array<string, list<string>> */
    private array $footnotes = [];

    /** @var array<string, int> etichetta => numero, assegnato all'atto della citazione */
    private array $footnoteNumbers = [];

    /** @var array<string, int> */
    private array $footnoteCites = [];

    /** @var array<int, bool> */
    private array $footnoteRendered = [];

    /** @var list<array{level: int, id: string, text: string}> */
    private array $headings = [];

    /** @var array<string, true> */
    private array $usedIds = [];

    public function __construct(public readonly bool $allowRawHtml)
    {
    }

    public function protect(string $html): string
    {
        $this->protected[] = $html;

        return self::OPEN . (count($this->protected) - 1) . self::CLOSE;
    }

    public function placeholderMark(): int
    {
        return count($this->protected);
    }

    /**
     * Scarta i segnaposto creati dopo `$mark`. Serve a `Parser::line()`, che
     * riusa lo stesso contesto per molti frammenti: senza questo la tabella
     * crescerebbe a ogni chiamata.
     */
    public function releasePlaceholders(int $mark): void
    {
        if ($mark >= 0 && $mark < count($this->protected)) {
            $this->protected = array_slice($this->protected, 0, $mark);
        }
    }

    public function restore(string $html): string
    {
        if ($this->protected === [] || !str_contains($html, self::OPEN)) {
            return $html;
        }

        $map = [];
        foreach ($this->protected as $index => $value) {
            $map[self::OPEN . $index . self::CLOSE] = $value;
        }

        return strtr($html, $map);
    }

    public function addReference(string $label, string $url, string $title): void
    {
        $key = self::normalize($label);
        if ($key !== '' && !isset($this->references[$key])) {
            $this->references[$key] = ['url' => $url, 'title' => $title];
        }
    }

    /** @return array{url: string, title: string}|null */
    public function reference(string $label): ?array
    {
        return $this->references[self::normalize($label)] ?? null;
    }

    /** @param list<string> $lines */
    public function addFootnote(string $label, array $lines): void
    {
        $key = self::normalize($label);
        if ($key !== '') {
            $this->footnotes[$key] = $lines;
        }
    }

    /**
     * Registra una citazione e restituisce numero e progressivo del rimando,
     * oppure null se la nota non è definita (il testo resta letterale).
     *
     * @return array{number: int, index: int}|null
     */
    public function citeFootnote(string $label): ?array
    {
        $key = self::normalize($label);
        if (!isset($this->footnotes[$key])) {
            return null;
        }

        $this->footnoteNumbers[$key] ??= count($this->footnoteNumbers) + 1;
        $this->footnoteCites[$key] = ($this->footnoteCites[$key] ?? 0) + 1;

        return ['number' => $this->footnoteNumbers[$key], 'index' => $this->footnoteCites[$key]];
    }

    /**
     * Note citate ma non ancora rese, in ordine di numero. Chiamarla di nuovo
     * intercetta le note citate dall'interno di un'altra nota.
     *
     * @return list<array{key: string, number: int, lines: list<string>}>
     */
    public function pendingFootnotes(): array
    {
        $pending = [];
        foreach ($this->footnoteNumbers as $key => $number) {
            if (isset($this->footnoteRendered[$number])) {
                continue;
            }
            $this->footnoteRendered[$number] = true;
            // PHP normalizza in intero le chiavi numeriche: `[^1]` va riportata a stringa.
            $pending[] = ['key' => (string) $key, 'number' => $number, 'lines' => $this->footnotes[$key]];
        }
        usort($pending, static fn(array $a, array $b): int => $a['number'] <=> $b['number']);

        return $pending;
    }

    public function footnoteCiteCount(string $key): int
    {
        return $this->footnoteCites[$key] ?? 1;
    }

    public function addHeading(int $level, string $id, string $text): void
    {
        $this->headings[] = ['level' => $level, 'id' => $id, 'text' => $text];
    }

    /** @return list<array{level: int, id: string, text: string}> */
    public function headings(): array
    {
        return $this->headings;
    }

    /** Slug univoco nel documento: i duplicati ricevono un suffisso numerico. */
    public function uniqueId(string $text): string
    {
        $base = self::slug($text);
        if ($base === '') {
            $base = 'sezione';
        }

        $candidate = $base;
        $n = 1;
        while (isset($this->usedIds[$candidate])) {
            $candidate = $base . '-' . (++$n);
        }
        $this->usedIds[$candidate] = true;

        return $candidate;
    }

    private static function slug(string $text): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        if (class_exists(\Transliterator::class)) {
            $transliterator = \Transliterator::create('Any-Latin; Latin-ASCII');
            if ($transliterator !== null) {
                $converted = $transliterator->transliterate($text);
                if (is_string($converted)) {
                    $text = $converted;
                }
            }
        }

        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/[^\p{L}\p{N}]+/u', '-', $text) ?? $text;

        return trim($text, '-');
    }

    private static function normalize(string $label): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $label)), 'UTF-8');
    }
}
