<?php

declare(strict_types=1);

namespace Noblogs\Controllers\Site;

use Noblogs\Core\Response;
use Noblogs\Core\Url;
use Noblogs\Markdown\Renderer;
use Noblogs\Models\Post;
use Noblogs\Support\Dates;

/**
 * Feed Atom e RSS.
 *
 * Il contenuto viene incluso per intero: chi legge con un aggregatore non
 * dovrebbe essere costretto ad aprire il sito per finire un articolo.
 */
final class FeedController extends SiteController
{
    private const PER_PAGE = 20;

    public function index(): Response
    {
        [$includeTags, $excludeTags] = $this->requestedTags();

        $posts = Post::published($this->blog, [
            'tags'         => $includeTags,
            'exclude_tags' => $excludeTags,
            'limit'        => self::PER_PAGE,
        ]);

        $isRss = $this->request->query('type') === 'rss'
            || str_contains($this->request->path, 'rss');

        $xml = $isRss ? $this->renderRss($posts) : $this->renderAtom($posts);

        return $this->applyPublicCache(
            Response::xml($xml)->withHeader(
                'Content-Type',
                ($isRss ? 'application/rss+xml' : 'application/atom+xml') . '; charset=UTF-8'
            )
        );
    }

    /** @param list<Post> $posts */
    private function renderAtom(array $posts): string
    {
        $root = Url::blogRoot($this->blog);
        $updated = $posts !== []
            ? Dates::parse($posts[0]->updated_at)
            : Dates::parse($this->blog->updated_at);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<feed xmlns="http://www.w3.org/2005/Atom" xml:lang="' . self::x($this->blog->displayLang()) . '">' . "\n"
            . '  <title>' . self::x($this->blog->title) . '</title>' . "\n"
            . '  <subtitle>' . self::x($this->blog->description()) . '</subtitle>' . "\n"
            . '  <link href="' . self::x($root . '/feed/') . '" rel="self" type="application/atom+xml"/>' . "\n"
            . '  <link href="' . self::x($root . '/') . '" rel="alternate" type="text/html"/>' . "\n"
            . '  <id>' . self::x($root . '/') . '</id>' . "\n"
            . '  <updated>' . self::x(Dates::iso($updated)) . '</updated>' . "\n"
            . '  <generator uri="https://noblogs.dev">Noblogs</generator>' . "\n";

        foreach ($posts as $post) {
            $url = Url::post($this->blog, $post->slug);
            $xml .= '  <entry>' . "\n"
                . '    <title>' . self::x($post->title) . '</title>' . "\n"
                . '    <link href="' . self::x($url) . '" rel="alternate" type="text/html"/>' . "\n"
                . '    <id>' . self::x($url) . '</id>' . "\n"
                . '    <published>' . self::x(Dates::iso($post->publishedAt())) . '</published>' . "\n"
                . '    <updated>' . self::x(Dates::iso($post->updatedAt())) . '</updated>' . "\n"
                . '    <summary>' . self::x($post->description()) . '</summary>' . "\n";

            foreach ($post->tagList() as $tag) {
                $xml .= '    <category term="' . self::x($tag) . '"/>' . "\n";
            }

            $xml .= '    <content type="html">' . self::x($this->contentFor($post)) . '</content>' . "\n"
                . '  </entry>' . "\n";
        }

        return $xml . '</feed>' . "\n";
    }

    /** @param list<Post> $posts */
    private function renderRss(array $posts): string
    {
        $root = Url::blogRoot($this->blog);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" '
            . 'xmlns:content="http://purl.org/rss/1.0/modules/content/">' . "\n"
            . '<channel>' . "\n"
            . '  <title>' . self::x($this->blog->title) . '</title>' . "\n"
            . '  <link>' . self::x($root . '/') . '</link>' . "\n"
            . '  <description>' . self::x($this->blog->description()) . '</description>' . "\n"
            . '  <language>' . self::x($this->blog->displayLang()) . '</language>' . "\n"
            . '  <atom:link href="' . self::x($root . '/rss/') . '" rel="self" type="application/rss+xml"/>' . "\n"
            . '  <generator>Noblogs</generator>' . "\n";

        foreach ($posts as $post) {
            $url = Url::post($this->blog, $post->slug);
            $xml .= '  <item>' . "\n"
                . '    <title>' . self::x($post->title) . '</title>' . "\n"
                . '    <link>' . self::x($url) . '</link>' . "\n"
                . '    <guid isPermaLink="true">' . self::x($url) . '</guid>' . "\n"
                . '    <pubDate>' . self::x(Dates::rfc822($post->publishedAt())) . '</pubDate>' . "\n"
                . '    <description>' . self::x($post->description()) . '</description>' . "\n";

            foreach ($post->tagList() as $tag) {
                $xml .= '    <category>' . self::x($tag) . '</category>' . "\n";
            }

            $xml .= '    <content:encoded><![CDATA[' . $this->contentForCdata($post) . ']]></content:encoded>' . "\n"
                . '  </item>' . "\n";
        }

        return $xml . '</channel>' . "\n" . '</rss>' . "\n";
    }

    /**
     * Il contenuto per il feed va reso con i link assoluti, perché un
     * aggregatore non conosce il dominio di origine.
     */
    private function contentFor(Post $post): string
    {
        $html = Renderer::post($this->blog, $post);
        $root = rtrim(Url::blogRoot($this->blog), '/');

        return preg_replace_callback(
            '/\b(href|src)="\/([^"]*)"/i',
            static fn(array $m): string => $m[1] . '="' . $root . '/' . $m[2] . '"',
            $html
        ) ?? $html;
    }

    private function contentForCdata(Post $post): string
    {
        // Una sequenza ]]> dentro il contenuto chiuderebbe la sezione CDATA.
        return str_replace(']]>', ']]&gt;', $this->contentFor($post));
    }

    private static function x(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
