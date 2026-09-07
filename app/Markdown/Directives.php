<?php

declare(strict_types=1);

namespace Noblogs\Markdown;

use Noblogs\Core\Csrf;
use Noblogs\Core\Url;
use Noblogs\Models\Blog;
use Noblogs\Models\Post;
use Noblogs\Support\Dates;
use Noblogs\Support\Str;

/**
 * Direttive scritte nel contenuto, nella forma {{ nome|filtro:valore }}.
 *
 * Servono a inserire elementi dinamici — l'elenco dei post, la nuvola dei tag,
 * il modulo di iscrizione — senza chiedere all'autore di scrivere HTML.
 *
 * Vengono espanse dopo la conversione del markdown e dopo la bonifica: l'HTML
 * che producono è generato qui e non deve passare per il sanitizzatore.
 */
final class Directives
{
    /** Direttive che sostituiscono un intero paragrafo. */
    private const BLOCK = [
        'posts', 'post_list', 'elenco_post', 'tags', 'tag_cloud', 'nuvola_tag',
        'toc', 'indice', 'subscribe', 'email_signup', 'iscrizione', 'search',
        'cerca', 'archive', 'archivio', 'next_post', 'previous_post',
        'post_successivo', 'post_precedente', 'post_nav',
    ];

    public function __construct(
        private Blog $blog,
        private ?Post $post = null,
        /** Titoli raccolti dal parser, per l'indice. */
        private array $headings = [],
    ) {
    }

    /** @param list<array{level:int,id:string,text:string}> $headings */
    public function withHeadings(array $headings): self
    {
        $this->headings = $headings;
        return $this;
    }

    public function apply(string $html): string
    {
        if (!str_contains($html, '{{')) {
            return $html;
        }

        // Le direttive dentro un blocco di codice sono un esempio, non un
        // comando: si mettono da parte e si rimettono a posto alla fine.
        [$html, $protected] = self::protectCode($html);

        // Un paragrafo che contiene solo una direttiva di blocco viene
        // sostituito per intero: un <ul> dentro un <p> non è HTML valido.
        $html = preg_replace_callback(
            '#<p>\s*\{\{\s*([^}]+?)\s*\}\}\s*</p>#u',
            fn(array $m): string => $this->expand(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'), true),
            $html
        ) ?? $html;

        $html = preg_replace_callback(
            '/\{\{\s*([^}]+?)\s*\}\}/u',
            fn(array $m): string => $this->expand(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'), false),
            $html
        ) ?? $html;

        return self::restoreCode($html, $protected);
    }

    /**
     * @return array{0:string,1:array<string,string>}
     */
    private static function protectCode(string $html): array
    {
        $protected = [];
        $html = preg_replace_callback(
            '#<(pre|code)\b[^>]*>.*?</\1>#is',
            static function (array $m) use (&$protected): string {
                $key = "\x00nb-code-" . count($protected) . "\x00";
                $protected[$key] = $m[0];
                return $key;
            },
            $html
        ) ?? $html;

        return [$html, $protected];
    }

    /** @param array<string,string> $protected */
    private static function restoreCode(string $html, array $protected): string
    {
        return $protected === [] ? $html : strtr($html, $protected);
    }

    private function expand(string $directive, bool $isBlock): string
    {
        $segments = array_map('trim', explode('|', $directive));
        $name = mb_strtolower(array_shift($segments) ?? '');

        $filters = [];
        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }
            [$key, $value] = str_contains($segment, ':')
                ? array_map('trim', explode(':', $segment, 2))
                : [$segment, 'true'];
            $filters[mb_strtolower($key)] = $value;
        }

        // Una direttiva di blocco incontrata a metà riga resta com'era: quasi
        // sempre è testo che parla delle direttive.
        if (!$isBlock && in_array($name, self::BLOCK, true)) {
            return '{{ ' . $directive . ' }}';
        }

        return match ($name) {
            'posts', 'post_list', 'elenco_post'        => $this->renderPostList($filters),
            'tags', 'tag_cloud', 'nuvola_tag'          => $this->renderTagCloud($filters),
            'toc', 'indice'                            => $this->renderToc($filters),
            'subscribe', 'email_signup', 'iscrizione'  => $this->renderSubscribeForm(),
            'search', 'cerca'                          => $this->renderSearchForm(),
            'archive', 'archivio'                      => $this->renderArchive(),
            'next_post', 'post_successivo'             => $this->renderNeighbour('next'),
            'previous_post', 'post_precedente'         => $this->renderNeighbour('previous'),
            'post_nav'                                 => $this->renderPostNav(),
            'blog_title', 'titolo_blog'                => e($this->blog->title),
            'blog_description', 'descrizione_blog'     => e($this->blog->description()),
            'blog_link', 'indirizzo_blog'              => e(Url::blogRoot($this->blog)),
            'blog_created', 'blog_creato'              => $this->renderDate(Dates::parse($this->blog->created_at)),
            'blog_last_posted', 'ultimo_post'          => Dates::since(Dates::parse($this->blog->last_posted_at), $this->blog->locale()),
            'post_count', 'numero_post'                => (string) Post::countPublished($this->blog),
            'post_title', 'titolo_post'                => e($this->post?->title ?? ''),
            'post_description', 'descrizione_post'     => e($this->post?->description() ?? ''),
            'post_link', 'indirizzo_post'              => $this->post !== null ? e(Url::post($this->blog, $this->post->slug)) : '',
            'post_date', 'data_post'                   => $this->renderDate($this->post?->publishedAt()),
            'post_updated', 'post_aggiornato'          => $this->renderDate($this->post?->updatedAt()),
            'year', 'anno'                             => Dates::now()->format('Y'),
            default                                    => '{{ ' . $directive . ' }}',
        };
    }

    // -----------------------------------------------------------------------
    // Direttive di blocco
    // -----------------------------------------------------------------------

    /** @param array<string,string> $filters */
    private function renderPostList(array $filters): string
    {
        $options = [
            'limit' => min(200, max(1, (int) ($filters['limit'] ?? $filters['limite'] ?? 50))),
            'order' => mb_strtolower($filters['order'] ?? $filters['ordine'] ?? 'desc'),
        ];

        if ($this->post !== null) {
            $options['exclude_id'] = $this->post->id;
        }
        if (isset($filters['pages']) || isset($filters['pagine'])) {
            $options['pages'] = true;
        }
        foreach (['from' => 'from', 'da' => 'from', 'to' => 'to', 'a' => 'to'] as $filter => $key) {
            if (isset($filters[$filter]) && Dates::parse($filters[$filter]) !== null) {
                $options[$key] = $filters[$filter];
            }
        }

        $tagFilter = $filters['tag'] ?? $filters['tags'] ?? null;
        if (is_string($tagFilter) && $tagFilter !== '') {
            [$include, $exclude] = self::splitTagFilter($tagFilter);
            $options['tags'] = $include;
            $options['exclude_tags'] = $exclude;
        }

        $posts = Post::published($this->blog, $options);
        if ($posts === []) {
            return '<p class="post-list-empty">' . e(__('blog.no_posts')) . '</p>';
        }

        $showDescription = self::isTrue($filters['description'] ?? $filters['descrizione'] ?? null);
        $showImage = self::isTrue($filters['image'] ?? $filters['immagine'] ?? null);
        $showDate = !self::isFalse($filters['date'] ?? $filters['data'] ?? null);

        $items = [];
        foreach ($posts as $post) {
            $url = Url::site('/' . $post->slug . '/');
            $item = '<li>';

            if ($showImage && $post->meta_image !== null && $post->meta_image !== '') {
                $item .= '<a class="post-list-image" href="' . e($url) . '">'
                    . '<img src="' . e($post->meta_image) . '" alt="" loading="lazy"></a>';
            }
            if ($showDate) {
                $item .= $this->timeTag($post->publishedAt()) . ' ';
            }
            $item .= '<a href="' . e($url) . '">' . e($post->title) . '</a>';

            if ($showDescription) {
                $description = $post->description();
                if ($description !== '') {
                    $item .= '<p class="post-list-description">' . e($description) . '</p>';
                }
            }

            $items[] = $item . '</li>';
        }

        return '<ul class="post-list">' . implode('', $items) . '</ul>';
    }

    /** @param array<string,string> $filters */
    private function renderTagCloud(array $filters): string
    {
        $tags = $this->blog->tags();
        if ($tags === []) {
            return '';
        }

        $limit = min(200, max(1, (int) ($filters['limit'] ?? $filters['limite'] ?? 100)));
        $items = [];
        foreach (array_slice($tags, 0, $limit) as $tag) {
            $url = Url::site('/' . $this->blog->blog_path . '/') . '?tag=' . rawurlencode($tag);
            $items[] = '<li><a href="' . e($url) . '">' . e($tag) . '</a></li>';
        }

        return '<ul class="tag-cloud">' . implode('', $items) . '</ul>';
    }

    /** @param array<string,string> $filters */
    private function renderToc(array $filters): string
    {
        $maxLevel = min(6, max(2, (int) ($filters['depth'] ?? $filters['profondita'] ?? 3)));
        $entries = array_values(array_filter(
            $this->headings,
            static fn(array $heading): bool => $heading['level'] >= 2 && $heading['level'] <= $maxLevel
        ));

        if ($entries === []) {
            return '';
        }

        $html = '<nav class="toc" aria-label="' . e(__('post.toc')) . '"><ol>';
        $current = $entries[0]['level'];
        foreach ($entries as $entry) {
            while ($entry['level'] > $current) {
                $html .= '<ol>';
                $current++;
            }
            while ($entry['level'] < $current) {
                $html .= '</ol>';
                $current--;
            }
            $html .= '<li><a href="#' . e($entry['id']) . '">' . e($entry['text']) . '</a></li>';
        }
        while ($current-- > $entries[0]['level']) {
            $html .= '</ol>';
        }

        return $html . '</ol></nav>';
    }

    private function renderSubscribeForm(): string
    {
        if (!$this->blog->subscriptions_active) {
            return '';
        }

        // Il campo esca è invisibile e vuoto: i moduli compilati da un
        // programma lo riempiono, quelli compilati da una persona no.
        return '<form class="subscribe-form" method="post" action="' . e(Url::site('/iscriviti/')) . '">'
            . '<label for="nb-subscribe-email">' . e(__('subscribe.label')) . '</label>'
            . '<input type="email" id="nb-subscribe-email" name="email" required '
            . 'placeholder="' . e(__('subscribe.placeholder')) . '" autocomplete="email">'
            . '<div class="nb-hp" aria-hidden="true">'
            . '<label for="nb-subscribe-website">' . e(__('form.leave_empty')) . '</label>'
            . '<input type="text" id="nb-subscribe-website" name="website" tabindex="-1" autocomplete="off">'
            . '</div>'
            . '<input type="hidden" name="ts" value="' . e(Csrf::sign('subscribe', 7200)) . '">'
            . '<button type="submit">' . e(__('subscribe.button')) . '</button>'
            . '</form>';
    }

    private function renderSearchForm(): string
    {
        return '<form class="search-form" method="get" action="' . e(Url::site('/cerca/')) . '" role="search">'
            . '<label for="nb-search">' . e(__('search.label')) . '</label>'
            . '<input type="search" id="nb-search" name="q" placeholder="' . e(__('search.placeholder')) . '">'
            . '<button type="submit">' . e(__('search.button')) . '</button>'
            . '</form>';
    }

    private function renderArchive(): string
    {
        $posts = Post::published($this->blog, ['limit' => 2000]);
        if ($posts === []) {
            return '';
        }

        $byYear = [];
        foreach ($posts as $post) {
            $year = $post->publishedAt()?->format('Y') ?? '—';
            $byYear[$year][] = $post;
        }

        $html = '<div class="archive">';
        foreach ($byYear as $year => $yearPosts) {
            $html .= '<h2 class="archive-year">' . e((string) $year) . '</h2><ul class="post-list">';
            foreach ($yearPosts as $post) {
                $html .= '<li>' . $this->timeTag($post->publishedAt(), 'j M')
                    . ' <a href="' . e(Url::site('/' . $post->slug . '/')) . '">' . e($post->title) . '</a></li>';
            }
            $html .= '</ul>';
        }

        return $html . '</div>';
    }

    private function renderNeighbour(string $direction): string
    {
        if ($this->post === null) {
            return '';
        }
        $neighbour = $this->post->neighbour($direction);
        if ($neighbour === null) {
            return '';
        }

        $label = $direction === 'next' ? __('post.next') : __('post.previous');
        $arrow = $direction === 'next' ? ' →' : '← ';
        $text = $direction === 'next' ? e($neighbour->title) . $arrow : $arrow . e($neighbour->title);

        return '<p class="post-neighbour ' . ($direction === 'next' ? 'next' : 'prev') . '">'
            . '<a href="' . e(Url::site('/' . $neighbour->slug . '/')) . '" '
            . 'rel="' . ($direction === 'next' ? 'next' : 'prev') . '" '
            . 'title="' . e($label) . '">' . $text . '</a></p>';
    }

    private function renderPostNav(): string
    {
        if ($this->post === null) {
            return '';
        }
        $previous = $this->post->neighbour('previous');
        $next = $this->post->neighbour('next');
        if ($previous === null && $next === null) {
            return '';
        }

        $html = '<nav class="post-nav">';
        $html .= $previous !== null
            ? '<a class="prev" rel="prev" href="' . e(Url::site('/' . $previous->slug . '/')) . '">← ' . e($previous->title) . '</a>'
            : '<span></span>';
        $html .= $next !== null
            ? '<a class="next" rel="next" href="' . e(Url::site('/' . $next->slug . '/')) . '">' . e($next->title) . ' →</a>'
            : '<span></span>';

        return $html . '</nav>';
    }

    // -----------------------------------------------------------------------
    // Supporto
    // -----------------------------------------------------------------------

    private function renderDate(?\DateTimeImmutable $date): string
    {
        return $date === null ? '' : $this->timeTag($date);
    }

    private function timeTag(?\DateTimeImmutable $date, ?string $format = null): string
    {
        if ($date === null) {
            return '';
        }
        // L'attributo datetime resta in UTC e uno script lo riscrive nel fuso
        // del lettore: così la pagina si può mettere in cache così com'è.
        return '<time datetime="' . e($date->format('c')) . '">'
            . e(Dates::format($date, $format ?? $this->blog->date_format, $this->blog->locale()))
            . '</time>';
    }

    /** @return array{0:list<string>,1:list<string>} */
    private static function splitTagFilter(string $value): array
    {
        $include = [];
        $exclude = [];
        foreach (Str::tags($value) as $tag) {
            if (str_starts_with($tag, '-')) {
                $exclude[] = mb_substr($tag, 1);
            } else {
                $include[] = $tag;
            }
        }
        return [$include, array_values(array_filter($exclude, 'strlen'))];
    }

    private static function isTrue(?string $value): bool
    {
        return $value !== null && in_array(mb_strtolower($value), ['1', 'true', 'sì', 'si', 'yes', 'on'], true);
    }

    private static function isFalse(?string $value): bool
    {
        return $value !== null && in_array(mb_strtolower($value), ['0', 'false', 'no', 'off'], true);
    }
}
