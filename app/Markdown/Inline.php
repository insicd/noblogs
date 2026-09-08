<?php

declare(strict_types=1);

namespace Noblogs\Markdown;

/**
 * Analisi degli span inline.
 *
 * Lo scanner avanza a byte: tutti i delimitatori markdown sono ASCII, e i byte
 * >= 0x80 vengono trattati come lettere, che è esattamente il comportamento
 * voluto dalle regole di "flanking" dell'enfasi (`**perché**` funziona) e
 * garantisce che una sequenza UTF-8 non venga mai spezzata a metà.
 *
 * Non esiste nessuna passata di sostituzione sull'intera stringa: il testo è
 * scomposto in nodi e solo i nodi di tipo testo ricevono escaping e
 * sostituzioni tipografiche. Codice, HTML grezzo e direttive diventano nodi
 * opachi e non possono più essere toccati.
 */
final class Inline
{
    private const MAX_DEPTH = 12;

    /** Caratteri su cui lo scanner si ferma; tutto il resto è testo. */
    private const SPECIALS = "\\`*_~^=[!<&{h\n";

    private const PUNCTUATION = '!"#$%&\'()*+,-./:;<=>?@[\\]^_`{|}~';

    /** Oltre questa soglia i delimitatori di enfasi restano letterali (anti-DoS). */
    private const MAX_DELIMITERS = 1000;

    /**
     * Lunghezza massima di un'etichetta di link, come in CommonMark. Senza un
     * tetto la ricerca della parentesi di chiusura diventa quadratica su input
     * del tipo `[[[[[[…`.
     */
    private const MAX_LABEL = 1000;

    private const RE_HTML_TAG = '/<(?:!--.*?--|\/?[A-Za-z][A-Za-z0-9\-]*(?:\s[^<>]*)?|![A-Za-z][^<>]*|\?[^<>]*\?)>/As';

    private const TYPOGRAPHY = [
        '(c)' => '©', '(C)' => '©',
        '(r)' => '®', '(R)' => '®',
        '(tm)' => '™', '(TM)' => '™',
        '+-' => '±',
        '...' => '…',
        '---' => '—',
        '--' => '–',
    ];

    public function __construct(private readonly Context $ctx)
    {
    }

    public function render(string $text, int $depth = 0, bool $allowLinks = true): string
    {
        if ($depth > self::MAX_DEPTH) {
            return Html::escape($text);
        }

        return $this->build($this->resolveEmphasis($this->tokenize($text, $depth, $allowLinks)));
    }

    /** Testo semplice, usato per gli attributi alt e per gli id dei titoli. */
    public function plain(string $text, int $depth = 0): string
    {
        $html = $this->ctx->restore($this->render($text, $depth + 1, false));

        return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    // --- scansione ---------------------------------------------------------

    /** @return list<array<string, mixed>> */
    private function tokenize(string $text, int $depth, bool $allowLinks): array
    {
        $nodes = [];
        $len = strlen($text);
        $pos = 0;
        $textStart = 0;
        $delimiters = 0;

        while ($pos < $len) {
            $pos += strcspn($text, self::SPECIALS, $pos);
            if ($pos >= $len) {
                break;
            }

            $token = $this->matchToken($text, $pos, $len, $depth, $allowLinks, $delimiters);
            if ($token === null) {
                // Nessuna costruzione riconosciuta: il carattere resta testo e
                // l'indice avanza comunque, così il ciclo non può stallare.
                $pos++;
                continue;
            }

            if ($token['flush'] > $textStart) {
                $nodes[] = ['k' => 'text', 'v' => substr($text, $textStart, $token['flush'] - $textStart)];
            }
            foreach ($token['nodes'] as $node) {
                $nodes[] = $node;
            }
            $pos += $token['consumed'];
            $textStart = $pos;
        }

        if ($len > $textStart) {
            $nodes[] = ['k' => 'text', 'v' => substr($text, $textStart)];
        }

        return $nodes;
    }

    /** @return array{nodes: list<array<string, mixed>>, consumed: int, flush: int}|null */
    private function matchToken(string $text, int $pos, int $len, int $depth, bool $allowLinks, int &$delimiters): ?array
    {
        return match ($text[$pos]) {
            "\n" => $this->hardBreak($text, $pos),
            '\\' => $this->backslash($text, $pos, $len),
            '`' => $this->codeSpan($text, $pos, $len),
            '*', '_' => $this->delimiterRun($text, $pos, $len, $delimiters),
            '=' => strspn($text, '=', $pos) >= 2 ? $this->delimiterRun($text, $pos, $len, $delimiters) : null,
            '~' => strspn($text, '~', $pos) >= 2
                ? $this->delimiterRun($text, $pos, $len, $delimiters)
                : $this->wrapped($text, $pos, $depth, '~', 'sub'),
            '^' => $this->wrapped($text, $pos, $depth, '^', 'sup'),
            '[' => $this->footnoteRef($text, $pos) ?? ($allowLinks ? $this->linkOrImage($text, $pos, $len, $depth, false) : null),
            '!' => $this->linkOrImage($text, $pos, $len, $depth, true),
            '<' => $this->angle($text, $pos),
            '&' => $this->entity($text, $pos),
            '{' => $this->directive($text, $pos, $len),
            'h' => $this->bareUrl($text, $pos),
            default => null,
        };
    }

    /** @return array{nodes: list<array<string, mixed>>, consumed: int, flush: int} */
    private static function token(array $nodes, int $consumed, int $flush): array
    {
        return ['nodes' => $nodes, 'consumed' => $consumed, 'flush' => $flush];
    }

    private static function raw(string $html): array
    {
        return ['k' => 'raw', 'v' => $html];
    }

    // --- costruzioni inline ------------------------------------------------

    /**
     * Un a capo dentro il paragrafo è un <br>, come in un editor.
     * CommonMark lo tratterebbe come spazio; qui Invio va a capo.
     * Due a capo restano un paragrafo nuovo: li spezza il parser a blocchi
     * prima di arrivare qui.
     *
     * I due spazi (o il backslash) prima dell'a capo, regola CommonMark,
     * restano accettati: gli spazi finali si mangiano insieme all'a capo.
     */
    private function hardBreak(string $text, int $pos): array
    {
        $spaces = 0;
        while ($pos - $spaces - 1 >= 0 && $text[$pos - $spaces - 1] === ' ') {
            $spaces++;
        }

        return self::token([self::raw("<br>\n")], 1, $pos - $spaces);
    }

    private function backslash(string $text, int $pos, int $len): ?array
    {
        if ($pos + 1 >= $len) {
            return null;
        }
        $next = $text[$pos + 1];

        if ($next === "\n") {
            return self::token([self::raw("<br>\n")], 2, $pos);
        }
        if (str_contains(self::PUNCTUATION, $next)) {
            // Il carattere sfuggito diventa un nodo opaco: non deve poter
            // fungere da delimitatore né subire sostituzioni tipografiche.
            return self::token([self::raw(Html::escape($next))], 2, $pos);
        }

        return null;
    }

    private function codeSpan(string $text, int $pos, int $len): array
    {
        $n = strspn($text, '`', $pos);
        $marker = str_repeat('`', $n);
        $close = false;

        for ($search = $pos + $n; $search < $len;) {
            $at = strpos($text, $marker, $search);
            if ($at === false) {
                break;
            }
            $run = strspn($text, '`', $at);
            if ($run === $n) {
                $close = $at;
                break;
            }
            $search = $at + $run;
        }

        if ($close === false) {
            return self::token([self::raw($marker)], $n, $pos);
        }

        $content = strtr(substr($text, $pos + $n, $close - $pos - $n), ["\n" => ' ']);
        if (str_starts_with($content, ' ') && str_ends_with($content, ' ') && trim($content) !== '') {
            $content = substr($content, 1, -1);
        }

        return self::token(
            [self::raw($this->ctx->protect('<code>' . Html::escape($content) . '</code>'))],
            $close + $n - $pos,
            $pos
        );
    }

    private function delimiterRun(string $text, int $pos, int $len, int &$delimiters): array
    {
        $char = $text[$pos];
        $run = strspn($text, $char, $pos);
        $n = ($char === '*' || $char === '_') ? $run : 2;

        if ($delimiters >= self::MAX_DELIMITERS) {
            return self::token([self::raw(str_repeat($char, $n))], $n, $pos);
        }
        $delimiters++;

        $before = $pos > 0 ? $text[$pos - 1] : "\n";
        $after = $pos + $n < $len ? $text[$pos + $n] : "\n";

        $left = !self::isSpace($after) && (!self::isPunct($after) || self::isSpace($before) || self::isPunct($before));
        $right = !self::isSpace($before) && (!self::isPunct($before) || self::isSpace($after) || self::isPunct($after));

        if ($char === '_') {
            // Regola specifica di `_`: dentro una parola (snake_case_word) il
            // trattino basso non apre né chiude enfasi.
            $open = $left && (!$right || self::isPunct($before));
            $close = $right && (!$left || self::isPunct($after));
        } else {
            $open = $left;
            $close = $right;
        }

        return self::token(
            [['k' => 'delim', 'c' => $char, 'n' => $n, 'len' => $n, 'open' => $open, 'close' => $close, 'before' => [], 'after' => []]],
            $n,
            $pos
        );
    }

    /** `H~2~O` e `6^a^`: delimitatori singoli che non ammettono spazi al loro interno. */
    private function wrapped(string $text, int $pos, int $depth, string $char, string $tag): ?array
    {
        $quoted = preg_quote($char, '/');
        if (preg_match('/' . $quoted . '([^\s' . $quoted . ']{1,80})' . $quoted . '/A', $text, $m, 0, $pos) !== 1) {
            return null;
        }

        return self::token(
            [self::raw('<' . $tag . '>' . $this->render($m[1], $depth + 1) . '</' . $tag . '>')],
            strlen($m[0]),
            $pos
        );
    }

    private function footnoteRef(string $text, int $pos): ?array
    {
        if (preg_match('/\[\^([^\]\s]{1,64})\]/A', $text, $m, 0, $pos) !== 1) {
            return null;
        }
        $cite = $this->ctx->citeFootnote($m[1]);
        if ($cite === null) {
            return null;
        }

        $suffix = $cite['index'] > 1 ? '-' . $cite['index'] : '';
        $html = '<sup id="fnref-' . $cite['number'] . $suffix . '">'
            . '<a href="#fn-' . $cite['number'] . '">' . $cite['number'] . '</a></sup>';

        return self::token([self::raw($html)], strlen($m[0]), $pos);
    }

    private function linkOrImage(string $text, int $pos, int $len, int $depth, bool $image): ?array
    {
        $open = $image ? $pos + 1 : $pos;
        if ($open >= $len || $text[$open] !== '[') {
            return null;
        }

        $end = self::matchBracket($text, $open, $len);
        if ($end < 0) {
            return null;
        }

        $label = substr($text, $open + 1, $end - $open - 1);
        $after = $end + 1;
        $url = null;
        $title = '';

        if ($after < $len && $text[$after] === '(') {
            $dest = self::parseDestination($text, $after, $len);
            if ($dest !== null) {
                $url = $dest['url'];
                $title = $dest['title'];
                $after = $dest['end'];
            }
        }

        if ($url === null) {
            $refLabel = $label;
            if ($after < $len && $text[$after] === '[') {
                $refEnd = strpos($text, ']', $after + 1);
                if ($refEnd === false) {
                    return null;
                }
                $inner = substr($text, $after + 1, $refEnd - $after - 1);
                if (trim($inner) !== '') {
                    $refLabel = $inner;
                }
                $after = $refEnd + 1;
            }
            $reference = $this->ctx->reference($refLabel);
            if ($reference === null) {
                return null;
            }
            $url = $reference['url'];
            $title = $reference['title'];
        }

        $html = $image
            ? $this->image($label, $url, $title, $depth)
            : $this->anchor($label, $url, $title, $depth);

        return self::token([self::raw($html)], $after - $pos, $pos);
    }

    private function anchor(string $label, string $url, string $title, int $depth): string
    {
        $newTab = false;
        if (strlen($url) > 4 && strncasecmp($url, 'tab:', 4) === 0) {
            $newTab = true;
            $url = substr($url, 4);
        }

        $inner = $this->render($label, $depth + 1, false);
        $safe = Html::safeUrl($url, false);
        if ($safe === null) {
            return '<a class="link-blocked" title="Collegamento non consentito">' . $inner . '</a>';
        }

        $html = '<a href="' . Html::escape($safe) . '"';
        if ($title !== '') {
            $html .= ' title="' . Html::escape($title) . '"';
        }
        if ($newTab) {
            $html .= ' target="_blank" rel="noopener noreferrer"';
        }

        return $html . '>' . $inner . '</a>';
    }

    private function image(string $label, string $url, string $title, int $depth): string
    {
        $alt = $this->plain($label, $depth);
        $safe = Html::safeUrl($url, true);
        if ($safe === null) {
            return '<span class="image-blocked">' . Html::escape($alt) . '</span>';
        }

        $html = '<img src="' . Html::escape($safe) . '" alt="' . Html::escape($alt) . '"';
        if ($title !== '') {
            $html .= ' title="' . Html::escape($title) . '"';
        }

        return $html . '>';
    }

    private function angle(string $text, int $pos): ?array
    {
        if (preg_match('/<([A-Za-z][A-Za-z0-9+.\-]{1,31}:[^<>\x00-\x20]*)>/A', $text, $m, 0, $pos) === 1) {
            return self::token([self::raw($this->autolink(self::unescape($m[1]), $m[1]))], strlen($m[0]), $pos);
        }

        if (preg_match('/<([^\s<>@]{1,64}@[A-Za-z0-9](?:[A-Za-z0-9.\-]{0,60}[A-Za-z0-9])?\.[A-Za-z]{2,24})>/A', $text, $m, 0, $pos) === 1) {
            $mail = Html::escape($m[1]);

            return self::token([self::raw('<a href="mailto:' . $mail . '">' . $mail . '</a>')], strlen($m[0]), $pos);
        }

        if ($this->ctx->allowRawHtml && preg_match(self::RE_HTML_TAG, $text, $m, 0, $pos) === 1) {
            return self::token([self::raw($this->ctx->protect($m[0]))], strlen($m[0]), $pos);
        }

        return null;
    }

    private function autolink(string $url, string $label): string
    {
        $safe = Html::safeUrl($url, false);
        $label = Html::escape($label);

        return $safe === null
            ? '<a class="link-blocked" title="Collegamento non consentito">' . $label . '</a>'
            : '<a href="' . Html::escape($safe) . '">' . $label . '</a>';
    }

    private function entity(string $text, int $pos): ?array
    {
        // Le entità già valide passano intatte: escaparle darebbe `&amp;copy;`.
        if (preg_match('/&(?:#[0-9]{1,7}|#[xX][0-9A-Fa-f]{1,6}|[A-Za-z][A-Za-z0-9]{1,31});/A', $text, $m, 0, $pos) !== 1) {
            return null;
        }

        return self::token([self::raw($m[0])], strlen($m[0]), $pos);
    }

    private function directive(string $text, int $pos, int $len): ?array
    {
        if ($pos + 1 >= $len || $text[$pos + 1] !== '{') {
            return null;
        }
        $close = strpos($text, '}}', $pos + 2);
        if ($close === false || $close - $pos > 4096) {
            return null;
        }

        // Le direttive sono risolte da un componente a valle: devono uscire
        // byte per byte come sono entrate, senza escaping né tipografia.
        $raw = substr($text, $pos, $close + 2 - $pos);

        return self::token([self::raw($this->ctx->protect($raw))], strlen($raw), $pos);
    }

    private function bareUrl(string $text, int $pos): ?array
    {
        if ($pos > 0) {
            $prev = $text[$pos - 1];
            if (ctype_alnum($prev) || $prev === '/' || $prev === ':' || ord($prev) >= 0x80) {
                return null;
            }
        }
        if (preg_match('#https?://[^\s<>"\'\\\\]+#A', $text, $m, 0, $pos) !== 1) {
            return null;
        }

        $url = self::trimUrlTail($m[0]);
        if (preg_match('#^https?://[^/?\#]+#', $url) !== 1) {
            return null;
        }

        return self::token([self::raw($this->autolink($url, $url))], strlen($url), $pos);
    }

    /** La punteggiatura finale appartiene alla frase, non all'URL. */
    private static function trimUrlTail(string $url): string
    {
        while ($url !== '') {
            $last = $url[strlen($url) - 1];
            if (str_contains('.,;:!?\'"', $last)) {
                $url = substr($url, 0, -1);
                continue;
            }
            if ($last === ')' && substr_count($url, ')') > substr_count($url, '(')) {
                $url = substr($url, 0, -1);
                continue;
            }
            if ($last === ']' && substr_count($url, ']') > substr_count($url, '[')) {
                $url = substr($url, 0, -1);
                continue;
            }
            break;
        }

        return $url;
    }

    // --- enfasi ------------------------------------------------------------

    /**
     * Abbinamento dei delimitatori con una pila di aperture, secondo le regole
     * di flanking di CommonMark (inclusa la "regola del 3" che disambigua casi
     * come `*foo**bar**baz*`).
     *
     * @param list<array<string, mixed>> $nodes
     * @return list<array<string, mixed>>
     */
    private function resolveEmphasis(array $nodes): array
    {
        $stack = [];
        $count = count($nodes);

        for ($i = 0; $i < $count; $i++) {
            if ($nodes[$i]['k'] !== 'delim') {
                continue;
            }

            while ($nodes[$i]['close'] && $nodes[$i]['n'] > 0) {
                $found = -1;
                for ($s = count($stack) - 1; $s >= 0; $s--) {
                    $opener = $nodes[$stack[$s]];
                    if ($opener['c'] !== $nodes[$i]['c'] || $opener['n'] <= 0) {
                        continue;
                    }
                    if (self::blockedByRuleOfThree($opener, $nodes[$i])) {
                        continue;
                    }
                    $found = $s;
                    break;
                }
                if ($found < 0) {
                    break;
                }

                $j = $stack[$found];
                $this->pairEmphasis($nodes, $j, $i);
                // I delimitatori rimasti scoperti fra apertura e chiusura non
                // possono più abbinarsi: restano testo letterale.
                array_splice($stack, $found);
                if ($nodes[$j]['n'] > 0) {
                    $stack[] = $j;
                }
            }

            if ($nodes[$i]['open'] && $nodes[$i]['n'] > 0) {
                $stack[] = $i;
            }
        }

        return $nodes;
    }

    private static function blockedByRuleOfThree(array $opener, array $closer): bool
    {
        if (!$opener['open']) {
            return true;
        }
        if (!$opener['close'] && !$closer['open']) {
            return false;
        }

        return ($opener['len'] + $closer['len']) % 3 === 0
            && !($opener['len'] % 3 === 0 && $closer['len'] % 3 === 0);
    }

    /** @param list<array<string, mixed>> $nodes */
    private function pairEmphasis(array &$nodes, int $j, int $i): void
    {
        [$tag, $use] = match ($nodes[$j]['c']) {
            '~' => ['del', 2],
            '=' => ['mark', 2],
            default => $nodes[$j]['n'] >= 2 && $nodes[$i]['n'] >= 2 ? ['strong', 2] : ['em', 1],
        };

        $nodes[$j]['n'] -= $use;
        $nodes[$i]['n'] -= $use;

        // L'apertura più esterna è quella abbinata per ultima, quindi in testa.
        array_unshift($nodes[$j]['after'], '<' . $tag . '>');
        $nodes[$i]['before'][] = '</' . $tag . '>';
    }

    // --- resa --------------------------------------------------------------

    /** @param list<array<string, mixed>> $nodes */
    private function build(array $nodes): string
    {
        $out = '';
        $prev = "\n";

        foreach ($nodes as $node) {
            if ($node['k'] === 'text') {
                $out .= Html::escape($this->typography($node['v'], $prev));
                $prev = substr($node['v'], -1);
                continue;
            }

            if ($node['k'] === 'delim') {
                $literal = $node['n'] > 0 ? str_repeat($node['c'], $node['n']) : '';
                $out .= implode('', $node['before']) . Html::escape($literal) . implode('', $node['after']);
                // Subito dopo un'apertura di enfasi una virgoletta è di apertura
                // (`*"citazione"*`), dopo una chiusura è di chiusura.
                $prev = $literal === '' && $node['after'] !== [] && $node['before'] === [] ? ' ' : 'x';
                continue;
            }

            $out .= $node['v'];
            // Dopo un frammento opaco una virgoletta è di chiusura.
            $prev = 'x';
        }

        return $out;
    }

    private function typography(string $text, string $prev): string
    {
        return self::smartQuotes(strtr($text, self::TYPOGRAPHY), $prev);
    }

    private static function smartQuotes(string $text, string $prev): string
    {
        if (!str_contains($text, '"') && !str_contains($text, "'")) {
            return $text;
        }

        $out = '';
        $len = strlen($text);
        for ($i = 0; $i < $len; $i++) {
            $c = $text[$i];
            if ($c === '"' || $c === "'") {
                $opening = $prev === '' || self::isSpace($prev) || str_contains('([{-–—“‘', $prev);
                if ($c === '"') {
                    $out .= $opening ? '“' : '”';
                } else {
                    $out .= $opening ? '‘' : '’';
                }
            } else {
                $out .= $c;
            }
            $prev = $c;
        }

        return $out;
    }

    // --- utilità -----------------------------------------------------------

    /** Indice della parentesi quadra che chiude quella in `$start`, o -1. */
    private static function matchBracket(string $text, int $start, int $len): int
    {
        $depth = 0;
        $len = min($len, $start + self::MAX_LABEL);
        for ($i = $start; $i < $len; $i++) {
            $c = $text[$i];
            if ($c === '\\') {
                $i++;
                continue;
            }
            if ($c === '[') {
                $depth++;
            } elseif ($c === ']') {
                if (--$depth === 0) {
                    return $i;
                }
            }
        }

        return -1;
    }

    /** @return array{url: string, title: string, end: int}|null */
    private static function parseDestination(string $text, int $pos, int $len): ?array
    {
        $pos += 1 + strspn($text, " \t\n", $pos + 1);
        $url = '';

        if ($pos < $len && $text[$pos] === '<') {
            $close = strpos($text, '>', $pos);
            if ($close === false) {
                return null;
            }
            $url = substr($text, $pos + 1, $close - $pos - 1);
            $pos = $close + 1;
        } else {
            $start = $pos;
            $depth = 0;
            while ($pos < $len) {
                $c = $text[$pos];
                if ($c === '\\' && $pos + 1 < $len) {
                    $pos += 2;
                    continue;
                }
                if ($c === '(') {
                    $depth++;
                } elseif ($c === ')') {
                    if ($depth === 0) {
                        break;
                    }
                    $depth--;
                } elseif ($c === ' ' || $c === "\t" || $c === "\n") {
                    break;
                }
                $pos++;
            }
            $url = substr($text, $start, $pos - $start);
        }

        $pos += strspn($text, " \t\n", $pos);
        $title = '';

        if ($pos < $len && ($text[$pos] === '"' || $text[$pos] === "'")) {
            $close = strpos($text, $text[$pos], $pos + 1);
            if ($close === false) {
                return null;
            }
            $title = substr($text, $pos + 1, $close - $pos - 1);
            $pos = $close + 1 + strspn($text, " \t\n", $close + 1);
        }

        if ($pos >= $len || $text[$pos] !== ')') {
            return null;
        }

        return ['url' => self::unescape($url), 'title' => self::unescape($title), 'end' => $pos + 1];
    }

    private static function unescape(string $value): string
    {
        if (!str_contains($value, '\\')) {
            return $value;
        }

        return (string) preg_replace('/\\\\([' . preg_quote(self::PUNCTUATION, '/') . '])/', '$1', $value);
    }

    private static function isSpace(string $char): bool
    {
        return $char === ' ' || $char === "\t" || $char === "\n" || $char === "\r" || $char === '';
    }

    private static function isPunct(string $char): bool
    {
        return $char !== '' && str_contains(self::PUNCTUATION, $char);
    }
}
