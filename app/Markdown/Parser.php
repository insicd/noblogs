<?php

declare(strict_types=1);

namespace Noblogs\Markdown;

/**
 * Parser markdown per i contenuti di Noblogs.
 *
 * Scelte deliberate rispetto a CommonMark:
 *  - i blocchi di codice indentati a 4 spazi NON sono supportati: nella pratica
 *    gli utenti li producono per sbaglio indentando le liste;
 *  - le direttive `{{ ... }}` attraversano il parser inalterate, perché sono
 *    risolte da un componente a valle;
 *  - il contenuto di codice e HTML grezzo è messo da parte in segnaposto opachi
 *    e reinserito solo alla fine, così nessuna fase successiva può toccarlo.
 */
final class Parser
{
    /** Oltre questa profondità il contenuto annidato viene reso come testo. */
    private const MAX_DEPTH = 12;

    private const RE_ATX = '/^ {0,3}(#{1,6})(?:[ \t]+(.*?))?[ \t]*$/';
    private const RE_FENCE = '/^( {0,3})(`{3,}|~{3,})[ \t]*([^`\s]*)/';
    private const RE_QUOTE = '/^ {0,3}>/';
    private const RE_MARKER = '/^( {0,3})(?:([-*+])|([0-9]{1,9})([.)]))(?=[ \t]|$)/';
    private const RE_DEFINITION = '/^ {0,3}:[ \t]+(.*)$/';

    private const HTML_BLOCK_TAGS = 'address|article|aside|audio|base|basefont|blockquote|body|canvas|caption'
        . '|center|col|colgroup|dd|details|dialog|dir|div|dl|dt|embed|fieldset|figcaption|figure|footer|form'
        . '|frame|frameset|h1|h2|h3|h4|h5|h6|head|header|hr|html|iframe|legend|li|link|main|menu|meta|nav'
        . '|noscript|object|ol|optgroup|option|p|param|picture|pre|script|section|source|style|summary|svg'
        . '|table|tbody|td|textarea|tfoot|th|thead|title|tr|track|ul|video';

    private const CALLOUTS = [
        'note' => 'Nota',
        'tip' => 'Suggerimento',
        'warning' => 'Attenzione',
        'danger' => 'Pericolo',
        'info' => 'Informazione',
    ];

    private Context $ctx;
    private Inline $inline;

    public function __construct(private readonly bool $allowRawHtml = true)
    {
        $this->reset();
    }

    /** Converte un documento markdown in HTML. */
    public function text(string $markdown): string
    {
        $this->reset();

        $lines = explode("\n", $this->sanitize($markdown));
        $lines = $this->extractDefinitions($lines);

        $html = implode("\n", $this->blocks($lines, 0)) . $this->renderFootnotes();

        return $this->ctx->restore($html);
    }

    /** Converte solo lo span inline (per titoli, nav, didascalie): niente <p>. */
    public function line(string $markdown): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $this->sanitize($markdown)));

        // line() non azzera il contesto (i riferimenti del documento restano
        // utilizzabili) ma non deve nemmeno accumulare segnaposto a ogni chiamata.
        $mark = $this->ctx->placeholderMark();
        $html = $this->ctx->restore($this->inline->render($text, 0));
        $this->ctx->releasePlaceholders($mark);

        return $html;
    }

    /** @return list<array{level: int, id: string, text: string}> Indice dei titoli dell'ultimo documento reso. */
    public function headings(): array
    {
        return $this->ctx->headings();
    }

    // --- preparazione ------------------------------------------------------

    private function reset(): void
    {
        $this->ctx = new Context($this->allowRawHtml);
        $this->inline = new Inline($this->ctx);
    }

    private function sanitize(string $value): string
    {
        if (!mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }

        // I segnaposto interni usano \x02 e \x03: rimuoverli dall'input impedisce
        // che un documento possa forgiarne uno e farsi reinserire HTML arbitrario.
        $value = str_replace(["\x02", "\x03", "\0"], '', $value);
        $value = str_replace(["\r\n", "\r"], "\n", $value);

        if (str_starts_with($value, "\u{FEFF}")) {
            $value = substr($value, 3);
        }

        return self::expandTabs(rtrim($value, "\n"));
    }

    private static function expandTabs(string $value): string
    {
        if (!str_contains($value, "\t")) {
            return $value;
        }

        $out = [];
        foreach (explode("\n", $value) as $line) {
            while (($at = strpos($line, "\t")) !== false) {
                $line = substr($line, 0, $at) . str_repeat(' ', 4 - ($at % 4)) . substr($line, $at + 1);
            }
            $out[] = $line;
        }

        return implode("\n", $out);
    }

    /**
     * Raccoglie le definizioni di riferimento e di nota presenti al primo
     * livello, rimuovendole dal flusso. I blocchi recintati vengono saltati:
     * ciò che sembra una definizione dentro del codice non lo è.
     *
     * @param list<string> $lines
     * @return list<string>
     */
    private function extractDefinitions(array $lines): array
    {
        $out = [];
        $count = count($lines);

        for ($i = 0; $i < $count; $i++) {
            $line = $lines[$i];

            if (preg_match(self::RE_FENCE, $line, $m) === 1) {
                $out[] = $line;
                $closing = '/^ {0,3}' . preg_quote($m[2][0], '/') . '{' . strlen($m[2]) . ',}[ \t]*$/';
                for ($i++; $i < $count; $i++) {
                    $out[] = $lines[$i];
                    if (preg_match($closing, $lines[$i]) === 1) {
                        break;
                    }
                }
                continue;
            }

            if (preg_match('/^ {0,3}\[\^([^\]\s]{1,64})\]:[ \t]*(.*)$/', $line, $m) === 1) {
                $this->ctx->addFootnote($m[1], self::gatherFootnote($lines, $i, $count, $m[2]));
                continue;
            }

            if (preg_match('/^ {0,3}\[([^\]]{1,255})\]:[ \t]*(<[^<>]*>|\S+)(?:[ \t]+("[^"]*"|\'[^\']*\'|\([^()]*\)))?[ \t]*$/', $line, $m) === 1
                && !str_starts_with($m[1], '^')
            ) {
                $title = $m[3] ?? '';
                $this->ctx->addReference($m[1], trim($m[2], '<>'), $title === '' ? '' : substr($title, 1, -1));
                continue;
            }

            $out[] = $line;
        }

        return $out;
    }

    /**
     * @param list<string> $lines
     * @return list<string>
     */
    private static function gatherFootnote(array $lines, int &$i, int $count, string $first): array
    {
        $body = [$first];

        while ($i + 1 < $count) {
            $next = $lines[$i + 1];
            if (trim($next) === '') {
                // Una riga vuota prosegue la nota solo se seguita da testo indentato.
                if (!isset($lines[$i + 2]) || preg_match('/^ {2,}\S/', $lines[$i + 2]) !== 1) {
                    break;
                }
                $body[] = '';
                $i++;
                continue;
            }
            if (preg_match('/^ {2,}\S/', $next) !== 1) {
                break;
            }
            $body[] = (string) preg_replace('/^ {1,4}/', '', $next);
            $i++;
        }

        return $body;
    }

    // --- blocchi -----------------------------------------------------------

    /**
     * @param list<string> $lines
     * @return list<string>
     */
    private function blocks(array $lines, int $depth): array
    {
        if ($depth > self::MAX_DEPTH) {
            $text = trim(implode("\n", $lines));

            return $text === '' ? [] : ['<p>' . Html::escape($text) . '</p>'];
        }

        $out = [];
        $i = 0;
        $count = count($lines);

        while ($i < $count) {
            if (trim($lines[$i]) === '') {
                $i++;
                continue;
            }

            $start = $i;
            $html = $this->block($lines, $i, $count, $depth);

            if ($html !== '') {
                $out[] = $html;
            }
            if ($i <= $start) {
                // Rete di sicurezza: nessun gestore può lasciare l'indice fermo.
                $i = $start + 1;
            }
        }

        return $out;
    }

    /** @param list<string> $lines */
    private function block(array $lines, int &$i, int $count, int $depth): string
    {
        $line = $lines[$i];

        if (preg_match(self::RE_FENCE, $line) === 1) {
            return $this->fencedCode($lines, $i, $count);
        }
        if (preg_match(self::RE_ATX, $line, $m) === 1) {
            $i++;

            return $this->heading(strlen($m[1]), self::stripClosingHashes($m[2] ?? ''));
        }
        if (self::isThematicBreak($line)) {
            $i++;

            return '<hr>';
        }
        if (preg_match(self::RE_QUOTE, $line) === 1) {
            return $this->blockquote($lines, $i, $count, $depth);
        }
        if (self::isListStart($line)) {
            return $this->list($lines, $i, $count, $depth);
        }
        if (self::isTableStart($lines, $i)) {
            return $this->table($lines, $i, $count, $depth);
        }
        if ($this->isDefinitionStart($lines, $i)) {
            return $this->definitionList($lines, $i, $count, $depth);
        }
        if ($this->allowRawHtml && $this->isHtmlBlock($line)) {
            return $this->htmlBlock($lines, $i, $count);
        }

        return $this->paragraph($lines, $i, $count, $depth);
    }

    /** @param list<string> $lines */
    private function fencedCode(array $lines, int &$i, int $count): string
    {
        preg_match(self::RE_FENCE, $lines[$i], $m);
        $indent = strlen($m[1]);
        $closing = '/^ {0,3}' . preg_quote($m[2][0], '/') . '{' . strlen($m[2]) . ',}[ \t]*$/';
        $language = (string) preg_replace('/[^A-Za-z0-9_+#.-]/', '', $m[3]);
        $body = [];

        for ($i++; $i < $count; $i++) {
            if (preg_match($closing, $lines[$i]) === 1) {
                $i++;
                break;
            }
            // Un fence non chiuso arriva fino in fondo al documento: nessun errore.
            $body[] = $indent > 0 ? (string) preg_replace('/^ {1,' . $indent . '}/', '', $lines[$i]) : $lines[$i];
        }

        $class = $language !== '' ? ' class="language-' . Html::escape($language) . '"' : '';
        $code = $body === [] ? '' : Html::escape(implode("\n", $body)) . "\n";

        return $this->ctx->protect('<pre><code' . $class . '>' . $code . '</code></pre>');
    }

    private function heading(int $level, string $raw): string
    {
        $html = $this->inline->render($raw, 0);
        $text = $this->inline->plain($raw);
        $id = $this->ctx->uniqueId($text);
        $this->ctx->addHeading($level, $id, $text);

        return '<h' . $level . ' id="' . Html::escape($id) . '">' . $html . '</h' . $level . '>';
    }

    private static function stripClosingHashes(string $text): string
    {
        return (string) preg_replace('/(?:^|[ \t])#+[ \t]*$/', '', $text);
    }

    /** @param list<string> $lines */
    private function paragraph(array $lines, int &$i, int $count, int $depth): string
    {
        $buffer = [];

        while ($i < $count) {
            $line = $lines[$i];
            if (trim($line) === '') {
                $i++;
                break;
            }
            if ($buffer !== [] && preg_match('/^ {0,3}(=+|-+)[ \t]*$/', $line, $m) === 1) {
                $i++;

                return $this->heading($m[1][0] === '=' ? 1 : 2, trim(implode(' ', array_map('trim', $buffer))));
            }
            if ($buffer !== [] && $this->interrupts($lines, $i)) {
                break;
            }
            $buffer[] = ltrim($line, " \t");
            $i++;
        }

        $text = rtrim(implode("\n", $buffer));

        return $text === '' ? '' : '<p>' . $this->inline->render($text, $depth) . '</p>';
    }

    /** @param list<string> $lines */
    private function blockquote(array $lines, int &$i, int $count, int $depth): string
    {
        $inner = [];

        while ($i < $count) {
            $line = $lines[$i];
            if (preg_match('/^ {0,3}> ?(.*)$/', $line, $m) === 1) {
                $inner[] = $m[1];
                $i++;
                continue;
            }
            if (trim($line) === '' || $inner === [] || $this->interrupts($lines, $i)) {
                break;
            }
            $inner[] = ltrim($line, " \t");
            $i++;
        }

        if (preg_match('/^\[!(NOTE|TIP|WARNING|DANGER|INFO)\][ \t]*(.*)$/i', $inner[0] ?? '', $m) === 1) {
            return $this->callout(strtolower($m[1]), trim($m[2]), array_slice($inner, 1), $depth);
        }

        $body = implode("\n", $this->blocks($inner, $depth + 1));

        return $body === '' ? '<blockquote></blockquote>' : "<blockquote>\n" . $body . "\n</blockquote>";
    }

    /** @param list<string> $lines */
    private function callout(string $type, string $title, array $lines, int $depth): string
    {
        $heading = $title === ''
            ? Html::escape(self::CALLOUTS[$type])
            : $this->inline->render($title, $depth + 1);
        $body = implode("\n", $this->blocks(array_values($lines), $depth + 1));

        return '<div class="callout callout-' . $type . '">' . "\n"
            . '<p class="callout-title">' . $heading . '</p>' . "\n"
            . ($body === '' ? '' : $body . "\n")
            . '</div>';
    }

    // --- liste -------------------------------------------------------------

    private static function isListStart(string $line): bool
    {
        return preg_match(self::RE_MARKER, $line) === 1;
    }

    /** @param list<string> $lines */
    private function list(array $lines, int &$i, int $count, int $depth): string
    {
        preg_match(self::RE_MARKER, $lines[$i], $first);
        $ordered = $first[2] === '';
        $bullet = $first[2];
        $delimiter = $first[4] ?? '';
        $start = $ordered ? (int) $first[3] : 1;

        /** @var list<list<string>> $items */
        $items = [];
        $itemIndent = 0;
        $blank = false;
        $loose = false;

        while ($i < $count) {
            $line = $lines[$i];

            if (trim($line) === '') {
                $blank = true;
                $i++;
                continue;
            }

            $indent = strspn($line, ' ');

            // Contenuto indentato: appartiene all'elemento corrente, comprese
            // le liste annidate. Va controllato prima del riconoscimento di un
            // nuovo marcatore, altrimenti `  - b` sembrerebbe un fratello.
            if ($items !== [] && $indent >= $itemIndent) {
                if ($blank) {
                    $items[array_key_last($items)][] = '';
                    $loose = true;
                    $blank = false;
                }
                $items[array_key_last($items)][] = substr($line, $itemIndent);
                $i++;
                continue;
            }

            if (preg_match(self::RE_MARKER, $line, $m) === 1) {
                if ($m[2] !== $bullet || ($ordered && $m[4] !== $delimiter)) {
                    break;
                }
                if ($blank) {
                    $loose = true;
                    $blank = false;
                }
                $marker = strlen($m[2] !== '' ? $m[2] : $m[3] . $m[4]);
                $afterMarker = $indent + $marker;
                $spaces = strspn($line, ' ', $afterMarker);
                $rest = substr($line, $afterMarker + $spaces);
                $itemIndent = ($spaces >= 1 && $spaces <= 4 && $rest !== '')
                    ? $afterMarker + $spaces
                    : $afterMarker + 1;
                $items[] = [$rest];
                $i++;
                continue;
            }

            if ($items === [] || $blank || $this->interrupts($lines, $i)) {
                break;
            }
            $items[array_key_last($items)][] = ltrim($line, " \t");
            $i++;
        }

        $tag = $ordered ? 'ol' : 'ul';
        $attr = $ordered && $start !== 1 ? ' start="' . $start . '"' : '';
        $html = '<' . $tag . $attr . ">\n";
        foreach ($items as $item) {
            $html .= $this->listItem($item, $loose, $depth + 1);
        }

        return $html . '</' . $tag . '>';
    }

    /** @param list<string> $lines */
    private function listItem(array $lines, bool $loose, int $depth): string
    {
        $checked = null;
        if (preg_match('/^\[([ xX])\](?:[ \t]+(.*))?$/', $lines[0] ?? '', $m) === 1) {
            $checked = $m[1] !== ' ';
            $lines[0] = $m[2] ?? '';
        }

        $blocks = $this->blocks($lines, $depth);
        if (!$loose) {
            $blocks = array_map(self::unwrapParagraph(...), $blocks);
        }
        $content = implode("\n", $blocks);

        if ($checked === null) {
            return '<li>' . $content . "</li>\n";
        }

        return '<li class="task"><input type="checkbox" disabled' . ($checked ? ' checked' : '') . '> '
            . $content . "</li>\n";
    }

    private static function unwrapParagraph(string $block): string
    {
        if (str_starts_with($block, '<p>') && str_ends_with($block, '</p>')) {
            $inner = substr($block, 3, -4);
            if (!str_contains($inner, '<p>')) {
                return $inner;
            }
        }

        return $block;
    }

    // --- tabelle -----------------------------------------------------------

    /** @param list<string> $lines */
    private static function isTableStart(array $lines, int $i): bool
    {
        if (!isset($lines[$i + 1]) || !str_contains($lines[$i], '|')) {
            return false;
        }

        $delimiter = trim($lines[$i + 1]);
        if ($delimiter === '' || !str_contains($delimiter, '-') || preg_match('/^[|: \t-]+$/', $delimiter) !== 1) {
            return false;
        }

        foreach (self::splitRow($delimiter) as $cell) {
            if (preg_match('/^:?-+:?$/', $cell) !== 1) {
                return false;
            }
        }

        return true;
    }

    /** @param list<string> $lines */
    private function table(array $lines, int &$i, int $count, int $depth): string
    {
        $header = self::splitRow($lines[$i]);
        $aligns = array_map(self::alignment(...), self::splitRow($lines[$i + 1]));
        $columns = count($header);
        $i += 2;

        $html = "<table>\n<thead>\n" . $this->row($header, $aligns, $columns, 'th', $depth) . "</thead>\n";
        $body = '';

        while ($i < $count) {
            $line = $lines[$i];
            if (trim($line) === '' || !str_contains($line, '|') || self::isThematicBreak($line)
                || preg_match(self::RE_FENCE, $line) === 1
            ) {
                break;
            }
            $body .= $this->row(self::splitRow($line), $aligns, $columns, 'td', $depth);
            $i++;
        }

        if ($body !== '') {
            $html .= "<tbody>\n" . $body . "</tbody>\n";
        }

        return $html . '</table>';
    }

    /**
     * @param list<string> $cells
     * @param list<string> $aligns
     */
    private function row(array $cells, array $aligns, int $columns, string $tag, int $depth): string
    {
        $html = "<tr>\n";
        for ($c = 0; $c < $columns; $c++) {
            // Le righe più corte o più lunghe dell'intestazione vengono
            // pareggiate: una tabella troncata non deve produrre HTML rotto.
            $align = $aligns[$c] ?? '';
            $attr = $align === '' ? '' : ' style="text-align:' . $align . '"';
            $html .= '<' . $tag . $attr . '>' . $this->inline->render($cells[$c] ?? '', $depth + 1) . '</' . $tag . ">\n";
        }

        return $html . "</tr>\n";
    }

    private static function alignment(string $spec): string
    {
        $left = str_starts_with($spec, ':');
        $right = str_ends_with($spec, ':');

        return match (true) {
            $left && $right => 'center',
            $right => 'right',
            $left => 'left',
            default => '',
        };
    }

    /** @return list<string> */
    private static function splitRow(string $row): array
    {
        $row = trim($row);
        $cells = [];
        $buffer = '';
        $len = strlen($row);

        for ($i = 0; $i < $len; $i++) {
            $c = $row[$i];
            if ($c === '\\' && $i + 1 < $len && $row[$i + 1] === '|') {
                $buffer .= '|';
                $i++;
                continue;
            }
            if ($c === '|') {
                $cells[] = trim($buffer);
                $buffer = '';
                continue;
            }
            $buffer .= $c;
        }
        $cells[] = trim($buffer);

        if ($cells !== [] && $cells[0] === '' && str_starts_with($row, '|')) {
            array_shift($cells);
        }
        if ($cells !== [] && end($cells) === '' && str_ends_with($row, '|')) {
            array_pop($cells);
        }

        return array_values($cells);
    }

    // --- liste di definizione ---------------------------------------------

    /** @param list<string> $lines */
    private function isDefinitionStart(array $lines, int $i): bool
    {
        return isset($lines[$i + 1])
            && preg_match(self::RE_DEFINITION, $lines[$i + 1]) === 1
            && preg_match(self::RE_DEFINITION, $lines[$i]) !== 1
            && !$this->interrupts($lines, $i);
    }

    /** @param list<string> $lines */
    private function definitionList(array $lines, int &$i, int $count, int $depth): string
    {
        $html = "<dl>\n";

        while ($i < $count && $this->isDefinitionStart($lines, $i)) {
            while ($i < $count && trim($lines[$i]) !== '' && preg_match(self::RE_DEFINITION, $lines[$i]) !== 1) {
                $html .= '<dt>' . $this->inline->render(trim($lines[$i]), $depth + 1) . "</dt>\n";
                $i++;
            }

            while ($i < $count && preg_match(self::RE_DEFINITION, $lines[$i], $m) === 1) {
                $body = [$m[1]];
                $i++;
                while ($i < $count && trim($lines[$i]) !== ''
                    && preg_match(self::RE_DEFINITION, $lines[$i]) !== 1
                    && strspn($lines[$i], ' ') >= 2
                ) {
                    $body[] = ltrim($lines[$i], " \t");
                    $i++;
                }
                $blocks = array_map(self::unwrapParagraph(...), $this->blocks($body, $depth + 1));
                $html .= '<dd>' . implode("\n", $blocks) . "</dd>\n";
            }

            if ($i < $count && trim($lines[$i]) === '') {
                $i++;
            }
        }

        return $html . '</dl>';
    }

    // --- HTML grezzo -------------------------------------------------------

    private function isHtmlBlock(string $line): bool
    {
        if (preg_match('/^ {0,3}<(?:!--|\?|![A-Za-z])/', $line) === 1) {
            return true;
        }
        if (preg_match('/^ {0,3}<\/?(?:' . self::HTML_BLOCK_TAGS . ')(?=[\s\/>]|$)/i', $line) === 1) {
            return true;
        }

        // Un tag completo che occupa da solo la riga apre comunque un blocco.
        return preg_match('/^ {0,3}<\/?[A-Za-z][A-Za-z0-9\-]*(?:\s[^<>]*)?\/?>[ \t]*$/', $line) === 1;
    }

    /** @param list<string> $lines */
    private function htmlBlock(array $lines, int &$i, int $count): string
    {
        $terminator = match (true) {
            preg_match('/^ {0,3}<!--/', $lines[$i]) === 1 => '-->',
            preg_match('/^ {0,3}<(script|style|pre|textarea)\b/i', $lines[$i], $m) === 1 => '</' . strtolower($m[1]) . '>',
            default => null,
        };

        $body = [];
        while ($i < $count) {
            $line = $lines[$i];
            if ($terminator === null && trim($line) === '') {
                break;
            }
            $body[] = $line;
            $i++;
            if ($terminator !== null && stripos($line, $terminator) !== false) {
                break;
            }
        }

        return $this->ctx->protect(implode("\n", $body));
    }

    // --- note a piè di pagina ---------------------------------------------

    private function renderFootnotes(): string
    {
        /** @var array<int, array{key: string, blocks: list<string>}> $items */
        $items = [];
        $guard = 0;

        // Il ciclo si ripete perché una nota può citarne un'altra.
        while (($pending = $this->ctx->pendingFootnotes()) !== [] && $guard++ < 100) {
            foreach ($pending as $note) {
                $blocks = $this->blocks($note['lines'], 1);
                $items[$note['number']] = ['key' => $note['key'], 'blocks' => $blocks === [] ? ['<p></p>'] : $blocks];
            }
        }

        if ($items === []) {
            return '';
        }
        ksort($items);

        $html = "\n" . '<div class="footnotes">' . "\n<hr>\n<ol>\n";
        foreach ($items as $number => $item) {
            $backrefs = '';
            $cites = $this->ctx->footnoteCiteCount($item['key']);
            for ($k = 1; $k <= $cites; $k++) {
                $suffix = $k > 1 ? '-' . $k : '';
                $backrefs .= ' <a href="#fnref-' . $number . $suffix . '" class="footnote-backref">&#8617;</a>';
            }

            $blocks = $item['blocks'];
            $last = array_key_last($blocks);
            $blocks[$last] = self::appendInline($blocks[$last], $backrefs);
            $html .= '<li id="fn-' . $number . '">' . implode("\n", $blocks) . "</li>\n";
        }

        return $html . "</ol>\n</div>";
    }

    private static function appendInline(string $block, string $suffix): string
    {
        return str_ends_with($block, '</p>')
            ? substr($block, 0, -4) . $suffix . '</p>'
            : $block . $suffix;
    }

    // --- riconoscimenti condivisi -----------------------------------------

    private static function isThematicBreak(string $line): bool
    {
        return preg_match('/^ {0,3}([-*_])[ \t]*(?:\1[ \t]*){2,}$/', $line) === 1;
    }

    /** Una riga che chiude il paragrafo in corso perché apre un altro blocco. */
    private function interrupts(array $lines, int $i): bool
    {
        $line = $lines[$i];

        return trim($line) === ''
            || preg_match(self::RE_FENCE, $line) === 1
            || preg_match(self::RE_ATX, $line) === 1
            || preg_match(self::RE_QUOTE, $line) === 1
            || self::isThematicBreak($line)
            || self::isTableStart($lines, $i)
            // Solo una lista numerata che parte da 1 può interrompere un paragrafo,
            // altrimenti "anno 1984\n2. cosa" spezzerebbe il testo.
            || preg_match('/^ {0,3}(?:[-*+]|1[.)])[ \t]+\S/', $line) === 1
            || ($this->allowRawHtml && $this->isHtmlBlock($line));
    }
}
