<?php

declare(strict_types=1);

namespace Noblogs\Markdown;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Ripulisce l'HTML prodotto dal markdown prima di mostrarlo ai lettori.
 *
 * Il parser lascia passare l'HTML scritto a mano, perché serve a chi vuole
 * incorporare un video o costruire un layout. Qui si decide che cosa di quel
 * markup sia accettabile: tag e attributi su lista bianca, nessun gestore di
 * eventi, nessuno schema di URL attivo, e per gli iframe solo host noti.
 *
 * Un blog può essere esentato da questo passaggio (campo allow_raw_html), ma è
 * una decisione che spetta all'amministrazione, non all'autore: chi scrive HTML
 * arbitrario su un sottodominio può costruirci sopra una pagina di phishing.
 */
final class Sanitizer
{
    /** @var array<string,list<string>> Tag ammessi e loro attributi specifici. */
    private const ALLOWED = [
        'p' => [], 'br' => [], 'hr' => [],
        'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
        'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [],
        'del' => ['datetime'], 'ins' => ['datetime'], 'mark' => [], 'small' => [],
        'sub' => [], 'sup' => [], 'abbr' => ['title'], 'kbd' => [], 'q' => ['cite'],
        'blockquote' => ['cite'], 'cite' => [],
        'ul' => [], 'ol' => ['start', 'reversed', 'type'], 'li' => ['value'],
        'dl' => [], 'dt' => [], 'dd' => [],
        'pre' => [], 'code' => [],
        'a' => ['href', 'target', 'rel', 'title', 'download'],
        'img' => ['src', 'alt', 'title', 'width', 'height', 'loading', 'decoding', 'srcset', 'sizes'],
        'figure' => [], 'figcaption' => [],
        'picture' => [], 'source' => ['src', 'srcset', 'type', 'media', 'sizes'],
        'video' => ['src', 'controls', 'poster', 'width', 'height', 'preload', 'loop', 'muted', 'playsinline'],
        'audio' => ['src', 'controls', 'preload', 'loop'],
        'track' => ['src', 'kind', 'srclang', 'label', 'default'],
        'table' => [], 'thead' => [], 'tbody' => [], 'tfoot' => [],
        'tr' => [], 'th' => ['colspan', 'rowspan', 'scope', 'abbr'], 'td' => ['colspan', 'rowspan'],
        'caption' => [], 'colgroup' => ['span'], 'col' => ['span'],
        'div' => [], 'span' => [], 'section' => [], 'article' => [], 'aside' => [],
        'header' => [], 'footer' => [], 'nav' => [], 'main' => [],
        'details' => ['open'], 'summary' => [],
        'time' => ['datetime'], 'data' => ['value'], 'wbr' => [],
        'input' => ['type', 'checked', 'disabled'], // solo per le task list
        'iframe' => ['src', 'width', 'height', 'title', 'allow', 'allowfullscreen', 'loading', 'frameborder'],
        'math' => [], 'semantics' => [], 'mrow' => [], 'mi' => [], 'mn' => [],
        'mo' => [], 'msup' => [], 'msub' => [], 'mfrac' => [], 'msqrt' => [], 'mtext' => [],
    ];

    /** Attributi ammessi ovunque. */
    private const GLOBAL_ATTRIBUTES = ['class', 'id', 'dir', 'lang', 'role', 'style'];

    /**
     * Host da cui è consentito incorporare un iframe. Un iframe verso un sito
     * qualunque può sovrapporsi alla pagina e chiedere credenziali, quindi la
     * lista resta corta e fatta di servizi di pubblicazione.
     */
    private const IFRAME_HOSTS = [
        'youtube.com', 'www.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com',
        'player.vimeo.com', 'vimeo.com',
        'w.soundcloud.com', 'bandcamp.com', 'open.spotify.com',
        'player.twitch.tv', 'archive.org', 'web.archive.org',
        'codepen.io', 'codesandbox.io', 'stackblitz.com', 'jsfiddle.net',
        'gist.github.com', 'replit.com',
        'umap.openstreetmap.fr', 'www.openstreetmap.org',
        'peertube.social', 'framatube.org', 'tube.kockatoo.org',
        'invidious.io', 'yewtu.be',
        'datawrapper.dwcdn.net', 'flo.uri.sh', 'public.flourish.studio',
    ];

    /** Schemi di URL considerati sicuri per href e src. */
    private const SAFE_SCHEMES = ['http', 'https', 'mailto', 'ftp', 'ftps', 'tel', 'xmpp', 'magnet', 'ipfs', 'gemini'];

    /** @var list<string> Host aggiuntivi configurati dall'amministrazione. */
    private array $extraIframeHosts;

    /** @param list<string> $extraIframeHosts */
    public function __construct(array $extraIframeHosts = [])
    {
        $this->extraIframeHosts = array_map('mb_strtolower', $extraIframeHosts);
    }

    public function clean(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        if (!class_exists(DOMDocument::class)) {
            // Senza ext-dom non si può fare una pulizia affidabile: meglio
            // togliere tutto il markup che lasciarne passare una parte.
            return strip_tags($html, '<p><br><strong><em><a><ul><ol><li><code><pre><blockquote><h1><h2><h3><h4><h5><h6><img>');
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        // Il commento XML forza l'interpretazione UTF-8 senza aggiungere nodi
        // visibili; LIBXML_HTML_NOIMPLIED evita che venga inserito un <body>.
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="noblogs-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET | LIBXML_NOENT
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return strip_tags($html);
        }

        $root = $document->getElementById('noblogs-root');
        if (!$root instanceof DOMElement) {
            return strip_tags($html);
        }

        $this->cleanNode($root, $document);

        $output = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $output .= $document->saveHTML($child);
        }

        return $output;
    }

    private function cleanNode(DOMNode $node, DOMDocument $document): void
    {
        // Si itera su una copia: la pulizia modifica l'albero mentre lo visita.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMComment) {
                // I commenti condizionali sono un vettore noto su vecchi browser.
                $child->parentNode?->removeChild($child);
                continue;
            }

            if (!$child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->nodeName);

            if (!isset(self::ALLOWED[$tag])) {
                $this->unwrap($child, $document, $tag);
                continue;
            }

            $this->cleanAttributes($child, $tag);

            if ($tag === 'iframe' && !$this->iframeIsAllowed($child)) {
                $child->parentNode?->removeChild($child);
                continue;
            }

            $this->cleanNode($child, $document);
        }
    }

    /**
     * Rimuove un tag non ammesso. Per script, style e simili sparisce anche il
     * contenuto; per gli altri il testo viene conservato, così un tag esotico
     * non fa perdere il paragrafo che contiene.
     */
    private function unwrap(DOMElement $element, DOMDocument $document, string $tag): void
    {
        static $dropContent = ['script', 'style', 'noscript', 'template', 'object', 'embed',
                               'applet', 'base', 'link', 'meta', 'form', 'button', 'select',
                               'textarea', 'option', 'frameset', 'frame', 'title', 'svg'];

        $parent = $element->parentNode;
        if ($parent === null) {
            return;
        }

        if (in_array($tag, $dropContent, true)) {
            $parent->removeChild($element);
            return;
        }

        $this->cleanNode($element, $document);
        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }
        $parent->removeChild($element);
    }

    private function cleanAttributes(DOMElement $element, string $tag): void
    {
        $allowed = array_merge(self::GLOBAL_ATTRIBUTES, self::ALLOWED[$tag]);

        foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
            $name = strtolower($attribute->nodeName);
            $value = $attribute->nodeValue ?? '';

            // I gestori di eventi sono la via più diretta all'esecuzione di
            // codice e non hanno nessun uso legittimo in un post.
            if (str_starts_with($name, 'on') || !in_array($name, $allowed, true)) {
                $element->removeAttribute($attribute->nodeName);
                continue;
            }

            if (in_array($name, ['href', 'src', 'cite', 'poster', 'srcset', 'download'], true)) {
                $safe = $name === 'srcset'
                    ? $this->sanitizeSrcset($value)
                    : $this->sanitizeUrl($value, $tag === 'img' || $tag === 'source');
                if ($safe === null) {
                    $element->removeAttribute($attribute->nodeName);
                    continue;
                }
                $element->setAttribute($attribute->nodeName, $safe);
            }

            if ($name === 'style') {
                $safe = $this->sanitizeStyle($value);
                if ($safe === '') {
                    $element->removeAttribute($attribute->nodeName);
                } else {
                    $element->setAttribute($attribute->nodeName, $safe);
                }
            }

            if ($name === 'target' && $value !== '') {
                // Una scheda aperta con target conserva un riferimento alla
                // pagina di origine se non si aggiunge noopener.
                $element->setAttribute('rel', 'noopener noreferrer');
            }
        }

        if ($tag === 'input') {
            // L'unico input legittimo è la casella delle task list.
            $element->setAttribute('type', 'checkbox');
            $element->setAttribute('disabled', 'disabled');
        }

        if ($tag === 'iframe') {
            $element->setAttribute('loading', 'lazy');
            $element->setAttribute('referrerpolicy', 'no-referrer');
        }

        if ($tag === 'img') {
            if (!$element->hasAttribute('loading')) {
                $element->setAttribute('loading', 'lazy');
            }
            if (!$element->hasAttribute('alt')) {
                $element->setAttribute('alt', '');
            }
        }

        if ($tag === 'a' && $element->hasAttribute('href')) {
            $href = $element->getAttribute('href');
            if (str_starts_with($href, 'http') && !$element->hasAttribute('rel')) {
                $element->setAttribute('rel', 'noopener');
            }
        }
    }

    private function sanitizeUrl(string $url, bool $allowDataImage = false): ?string
    {
        // Le entità sono già decodificate dal parser DOM; qui si tolgono gli
        // spazi e i caratteri di controllo che servono a spezzare "javascript:".
        $normalized = strtolower(preg_replace('/[\s\x00-\x20\x7f]+/', '', $url) ?? '');

        if ($normalized === '') {
            return null;
        }

        if (str_starts_with($normalized, 'data:')) {
            $isInertImage = (bool) preg_match('#^data:image/(png|jpe?g|gif|webp|avif);base64,#', $normalized);
            return $allowDataImage && $isInertImage ? $url : null;
        }

        // Un URL relativo o assoluto senza schema è sempre interno al blog.
        if (!preg_match('#^([a-z][a-z0-9+.-]*):#', $normalized, $matches)) {
            return $url;
        }

        return in_array($matches[1], self::SAFE_SCHEMES, true) ? $url : null;
    }

    private function sanitizeSrcset(string $value): ?string
    {
        $candidates = [];
        foreach (explode(',', $value) as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }
            $parts = preg_split('/\s+/', $candidate, 2);
            if ($parts === false || $this->sanitizeUrl($parts[0], true) === null) {
                continue;
            }
            $candidates[] = $candidate;
        }
        return $candidates === [] ? null : implode(', ', $candidates);
    }

    /**
     * Gli stili in linea servono per allineare un'immagine o colorare una
     * parola. Si tolgono le dichiarazioni che possono caricare risorse o
     * uscire dal flusso della pagina per coprirla.
     */
    private function sanitizeStyle(string $style): string
    {
        static $blocked = [
            'position', 'z-index', 'behavior', 'expression', '-moz-binding',
            'content', 'transform', 'filter', 'backdrop-filter', 'clip', 'clip-path',
            'animation', 'transition', 'pointer-events', 'mix-blend-mode',
        ];

        $declarations = [];
        foreach (explode(';', $style) as $declaration) {
            $declaration = trim($declaration);
            if ($declaration === '' || !str_contains($declaration, ':')) {
                continue;
            }
            [$property, $value] = array_map('trim', explode(':', $declaration, 2));
            $property = strtolower($property);
            $lowerValue = strtolower($value);

            if (in_array($property, $blocked, true)) {
                continue;
            }
            if (str_contains($lowerValue, 'url(')
                || str_contains($lowerValue, 'expression')
                || str_contains($lowerValue, 'javascript')
                || str_contains($lowerValue, '\\')) {
                continue;
            }
            if (!preg_match('/^[a-z-]+$/', $property)) {
                continue;
            }

            $declarations[] = $property . ': ' . $value;
        }

        return implode('; ', $declarations);
    }

    private function iframeIsAllowed(DOMElement $iframe): bool
    {
        $src = $iframe->getAttribute('src');
        if ($src === '') {
            return false;
        }
        $host = parse_url($src, PHP_URL_HOST);
        if (!is_string($host)) {
            return false;
        }
        $host = mb_strtolower($host);

        return in_array($host, self::IFRAME_HOSTS, true)
            || in_array($host, $this->extraIframeHosts, true);
    }
}
