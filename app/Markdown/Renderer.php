<?php

declare(strict_types=1);

namespace Noblogs\Markdown;

use Noblogs\Core\Config;
use Noblogs\Models\Blog;
use Noblogs\Models\Post;

/**
 * Trasforma il markdown di un blog in HTML pronto da mostrare.
 *
 * L'ordine dei passaggi conta: prima si converte il markdown, poi si bonifica
 * l'HTML scritto dall'autore, e solo alla fine si espandono le direttive, il
 * cui risultato è generato dal sistema e non va ripulito.
 */
final class Renderer
{
    private static ?Sanitizer $sanitizer = null;

    /**
     * Rende il contenuto di un post o di una pagina.
     */
    public static function post(Blog $blog, Post $post): string
    {
        return self::render($blog, $post->content ?? '', $post);
    }

    /**
     * Rende la homepage o un contenuto libero associato al blog.
     */
    public static function content(Blog $blog, string $markdown, ?Post $post = null): string
    {
        return self::render($blog, $markdown, $post);
    }

    /**
     * Rende la barra di navigazione: markdown inline, senza paragrafi.
     */
    public static function nav(Blog $blog): string
    {
        $nav = trim($blog->nav ?? '');
        if ($nav === '') {
            return '';
        }

        $parser = new Parser(false);
        $html = '';
        // Ogni riga della barra diventa un gruppo di link: chi vuole due
        // livelli di menu scrive due righe.
        foreach (explode("\n", $nav) as $line) {
            $line = trim($line);
            if ($line !== '') {
                $html .= $parser->line($line) . ' ';
            }
        }

        return trim($html);
    }

    /** Titolo con la sola formattazione inline (grassetto, corsivo, codice). */
    public static function inline(string $markdown): string
    {
        return (new Parser(false))->line($markdown);
    }

    private static function render(Blog $blog, string $markdown, ?Post $post): string
    {
        if (trim($markdown) === '') {
            return '';
        }

        $cacheKey = self::cacheKey($blog, $post, $markdown);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $parser = new Parser(true);
        $html = $parser->text($markdown);

        if (!self::allowsRawHtml($blog)) {
            $html = self::sanitizer()->clean($html);
        }

        $html = (new Directives($blog, $post))
            ->withHeadings($parser->headings())
            ->apply($html);

        // Le direttive producono elenchi di post e nuvole di tag che cambiano
        // a ogni pubblicazione: la cache dura poco e viene svuotata a ogni
        // salvataggio del blog.
        Cache::put($cacheKey, $html, 3600);

        return $html;
    }

    private static function allowsRawHtml(Blog $blog): bool
    {
        return $blog->allow_raw_html;
    }

    private static function sanitizer(): Sanitizer
    {
        return self::$sanitizer ??= new Sanitizer(
            array_map('strval', (array) Config::get('security.extra_iframe_hosts', []))
        );
    }

    private static function cacheKey(Blog $blog, ?Post $post, string $markdown): string
    {
        return 'render/' . $blog->id . '/' . ($post?->id ?? 0) . '/' . substr(sha1('br1|' . $markdown), 0, 16);
    }
}
