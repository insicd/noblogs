<?php

declare(strict_types=1);

namespace Noblogs\Models;

use Noblogs\Core\Csrf;
use Noblogs\Support\Dates;
use Noblogs\Support\Str;

/**
 * Post o pagina. La distinzione è il flag is_page: le pagine non compaiono
 * nell'elenco cronologico né nei feed, ma vivono nella stessa tabella perché
 * condividono tutto il resto (slug, markdown, metadati).
 */
final class Post extends Model
{
    protected static string $table = 'posts';

    protected static array $casts = [
        'id'                => 'int',
        'blog_id'           => 'int',
        'content_length'    => 'int',
        'is_page'           => 'bool',
        'is_published'      => 'bool',
        'make_discoverable' => 'bool',
        'hidden'            => 'bool',
        'upvotes'           => 'int',
        'shadow_votes'      => 'int',
        'score'             => 'float',
    ];

    public int $blog_id = 0;
    public string $uid = '';
    public string $title = '';
    public string $slug = '';
    public ?string $alias = null;
    public ?string $content = null;
    public int $content_length = 0;
    public bool $is_page = false;
    public bool $is_published = true;
    public bool $make_discoverable = true;
    public bool $hidden = false;
    public ?string $tags = null;
    public ?string $canonical_url = null;
    public ?string $meta_description = null;
    public ?string $meta_image = null;
    public ?string $lang = null;
    public ?string $class_name = null;
    public int $upvotes = 0;
    public int $shadow_votes = 0;
    public float $score = 0.0;
    public string $published_at = '';
    public ?string $first_published_at = null;
    public string $created_at = '';
    public string $updated_at = '';

    // -----------------------------------------------------------------------
    // Ricerca
    // -----------------------------------------------------------------------

    public static function findByUid(string $uid): ?self
    {
        return self::hydrateOrNull(self::db()->fetch('SELECT * FROM {{posts}} WHERE uid = ?', [$uid]));
    }

    public static function findForBlog(Blog $blog, int $id): ?self
    {
        return self::hydrateOrNull(self::db()->fetch(
            'SELECT * FROM {{posts}} WHERE blog_id = ? AND id = ?',
            [$blog->id, $id]
        ));
    }

    public static function findBySlug(Blog $blog, string $slug): ?self
    {
        return self::hydrateOrNull(self::db()->fetch(
            'SELECT * FROM {{posts}} WHERE blog_id = ? AND slug = ?',
            [$blog->id, mb_strtolower(trim($slug, '/'))]
        ));
    }

    public static function findByAlias(Blog $blog, string $alias): ?self
    {
        $alias = mb_strtolower(trim($alias, '/'));
        if ($alias === '') {
            return null;
        }
        return self::hydrateOrNull(self::db()->fetch(
            'SELECT * FROM {{posts}} WHERE blog_id = ? AND alias = ?',
            [$blog->id, $alias]
        ));
    }

    public static function slugTaken(Blog $blog, string $slug, int $exceptId = 0): bool
    {
        return self::db()->fetchColumn(
            'SELECT 1 FROM {{posts}} WHERE blog_id = ? AND slug = ? AND id <> ?',
            [$blog->id, $slug, $exceptId]
        ) !== null;
    }

    /** Slug libero, ottenuto aggiungendo un contatore se necessario. */
    public static function uniqueSlug(Blog $blog, string $desired, int $exceptId = 0): string
    {
        $base = Str::slug($desired, '-', true);
        if ($base === '') {
            $base = 'post';
        }
        $slug = $base;
        $suffix = 2;
        while (self::slugTaken($blog, $slug, $exceptId)) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }
        return $slug;
    }

    // -----------------------------------------------------------------------
    // Elenchi
    // -----------------------------------------------------------------------

    /**
     * Elenco dei post pubblicati di un blog.
     *
     * @param array{tags?:list<string>,exclude_tags?:list<string>,limit?:int,offset?:int,
     *              order?:string,pages?:bool,from?:string,to?:string,exclude_id?:int} $options
     * @return list<self>
     */
    public static function published(Blog $blog, array $options = []): array
    {
        [$sql, $params] = self::publishedQuery($blog, $options);
        $order = strtolower($options['order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY published_at $order, id $order";

        if (isset($options['limit'])) {
            $sql .= ' LIMIT ' . max(0, (int) $options['limit']);
            if (isset($options['offset'])) {
                $sql .= ' OFFSET ' . max(0, (int) $options['offset']);
            }
        }

        $posts = self::hydrateAll(self::db()->fetchAll($sql, $params));

        // Il filtro sui tag in SQL è approssimato (LIKE sul JSON): qui si fa
        // il confronto esatto, che è l'unico affidabile.
        if (!empty($options['tags']) || !empty($options['exclude_tags'])) {
            $posts = self::filterByTags($posts, $options['tags'] ?? [], $options['exclude_tags'] ?? []);
        }

        return $posts;
    }

    /** @param array<string,mixed> $options */
    public static function countPublished(Blog $blog, array $options = []): int
    {
        if (!empty($options['tags']) || !empty($options['exclude_tags'])) {
            // Con i tag serve il conteggio esatto, quindi si passa dai modelli.
            return count(self::published($blog, $options + ['limit' => 10000]));
        }
        [$sql, $params] = self::publishedQuery($blog, $options, 'COUNT(*)');
        return (int) self::db()->fetchColumn($sql, $params);
    }

    /**
     * @param array<string,mixed> $options
     * @return array{0:string,1:array<string,mixed>}
     */
    private static function publishedQuery(Blog $blog, array $options, string $select = '*'): array
    {
        $sql = "SELECT $select FROM {{posts}}
                WHERE blog_id = :blog_id
                  AND is_published = 1
                  AND hidden = 0
                  AND published_at <= UTC_TIMESTAMP()";
        $params = ['blog_id' => $blog->id];

        if (empty($options['pages'])) {
            $sql .= ' AND is_page = 0';
        }
        if (!empty($options['exclude_id'])) {
            $sql .= ' AND id <> :exclude_id';
            $params['exclude_id'] = (int) $options['exclude_id'];
        }
        if (!empty($options['from'])) {
            $sql .= ' AND published_at >= :from';
            $params['from'] = $options['from'];
        }
        if (!empty($options['to'])) {
            $sql .= ' AND published_at <= :to';
            $params['to'] = $options['to'];
        }
        foreach (array_values($options['tags'] ?? []) as $i => $tag) {
            $sql .= " AND tags LIKE :tag$i";
            $params["tag$i"] = '%' . self::escapeLike(json_encode($tag, JSON_UNESCAPED_UNICODE) ?: '') . '%';
        }

        return [$sql, $params];
    }

    /**
     * @param list<self>   $posts
     * @param list<string> $include
     * @param list<string> $exclude
     * @return list<self>
     */
    private static function filterByTags(array $posts, array $include, array $exclude): array
    {
        $include = array_map('mb_strtolower', $include);
        $exclude = array_map('mb_strtolower', $exclude);

        return array_values(array_filter($posts, static function (self $post) use ($include, $exclude): bool {
            $tags = array_map('mb_strtolower', $post->tagList());
            foreach ($include as $tag) {
                if (!in_array($tag, $tags, true)) {
                    return false;
                }
            }
            foreach ($exclude as $tag) {
                if (in_array($tag, $tags, true)) {
                    return false;
                }
            }
            return true;
        }));
    }

    /** Bozze e post programmati, per la dashboard. */
    /** @return list<self> */
    public static function forDashboard(Blog $blog, bool $pages = false): array
    {
        return self::hydrateAll(self::db()->fetchAll(
            'SELECT * FROM {{posts}} WHERE blog_id = ? AND is_page = ?
             ORDER BY published_at DESC, id DESC',
            [$blog->id, $pages ? 1 : 0]
        ));
    }

    public function neighbour(string $direction): ?self
    {
        $isNext = $direction === 'next';
        $comparison = $isNext ? '>' : '<';
        $order = $isNext ? 'ASC' : 'DESC';

        return self::hydrateOrNull(self::db()->fetch(
            "SELECT * FROM {{posts}}
             WHERE blog_id = ? AND is_page = 0 AND is_published = 1 AND hidden = 0
               AND published_at <= UTC_TIMESTAMP()
               AND (published_at $comparison ? OR (published_at = ? AND id $comparison ?))
             ORDER BY published_at $order, id $order
             LIMIT 1",
            [$this->blog_id, $this->published_at, $this->published_at, $this->id]
        ));
    }

    // -----------------------------------------------------------------------
    // Persistenza
    // -----------------------------------------------------------------------

    public static function make(Blog $blog): self
    {
        $post = new self();
        $post->blog_id = $blog->id;
        $post->uid = self::freshUid();
        $post->published_at = self::now();
        $post->created_at = $post->published_at;
        $post->updated_at = $post->published_at;
        return $post;
    }

    /**
     * Scrive il post. Restituisce l'id.
     *
     * @param array<string,mixed> $attributes
     */
    public function fill(array $attributes): void
    {
        static $allowed = [
            'title', 'slug', 'alias', 'content', 'is_page', 'is_published',
            'make_discoverable', 'hidden', 'tags', 'canonical_url',
            'meta_description', 'meta_image', 'lang', 'class_name', 'published_at',
        ];
        foreach (array_intersect_key($attributes, array_flip($allowed)) as $key => $value) {
            if (!property_exists($this, $key)) {
                continue;
            }
            $this->$key = is_bool($this->$key) ? (bool) $value : $value;
        }
    }

    public function save(): void
    {
        $this->content_length = mb_strlen(Str::plain($this->content ?? ''));
        $this->updated_at = self::now();

        $isLive = $this->is_published && !$this->is_page && $this->published_at <= self::now();
        if ($isLive && $this->first_published_at === null) {
            $this->first_published_at = $this->published_at;
        }

        $data = [
            'blog_id'            => $this->blog_id,
            'uid'                => $this->uid,
            'title'              => $this->title,
            'slug'               => $this->slug,
            'alias'              => $this->alias !== '' ? $this->alias : null,
            'content'            => $this->content,
            'content_length'     => $this->content_length,
            'is_page'            => $this->is_page ? 1 : 0,
            'is_published'       => $this->is_published ? 1 : 0,
            'make_discoverable'  => $this->make_discoverable ? 1 : 0,
            'hidden'             => $this->hidden ? 1 : 0,
            'tags'               => $this->tags,
            'canonical_url'      => $this->canonical_url,
            'meta_description'   => $this->meta_description,
            'meta_image'         => $this->meta_image,
            'lang'               => $this->lang,
            'class_name'         => $this->class_name,
            'upvotes'            => $this->upvotes,
            'shadow_votes'       => $this->shadow_votes,
            'score'              => $this->computeScore(),
            'published_at'       => $this->published_at,
            'first_published_at' => $this->first_published_at,
            'updated_at'         => $this->updated_at,
        ];

        if ($this->exists()) {
            $this->updateRow($data);
        } else {
            $this->created_at = $this->created_at !== '' ? $this->created_at : $this->updated_at;
            $this->insertRow($data + ['created_at' => $this->created_at]);
        }

        // Un post nuovo o modificato cambia anche gli elenchi generati dalle
        // direttive nelle altre pagine, non solo la propria.
        \Noblogs\Markdown\Cache::flushBlog($this->blog_id);
    }

    private static function freshUid(): string
    {
        do {
            $uid = Str::uid(20);
        } while (self::db()->fetchColumn('SELECT 1 FROM {{posts}} WHERE uid = ?', [$uid]) !== null);
        return $uid;
    }

    // -----------------------------------------------------------------------
    // Stato e presentazione
    // -----------------------------------------------------------------------

    public function isScheduled(): bool
    {
        return $this->is_published && $this->published_at > self::now();
    }

    public function isDraft(): bool
    {
        return !$this->is_published;
    }

    public function isVisible(): bool
    {
        return $this->is_published && !$this->hidden && $this->published_at <= self::now();
    }

    /** Token che permette di condividere una bozza senza pubblicarla. */
    public function previewToken(): string
    {
        return substr(Csrf::hmac('preview:' . $this->uid), 0, 16);
    }

    public function matchesPreviewToken(?string $token): bool
    {
        return is_string($token) && $token !== '' && hash_equals($this->previewToken(), $token);
    }

    /** @return list<string> */
    public function tagList(): array
    {
        return self::decodeList($this->tags);
    }

    /** @param list<string> $tags */
    public function setTags(array $tags): void
    {
        $this->tags = self::encodeList($tags);
    }

    public function publishedAt(): ?\DateTimeImmutable
    {
        return Dates::parse($this->published_at);
    }

    public function updatedAt(): ?\DateTimeImmutable
    {
        return Dates::parse($this->updated_at);
    }

    public function description(): string
    {
        if ($this->meta_description !== null && trim($this->meta_description) !== '') {
            return $this->meta_description;
        }
        return Str::limit(Str::plain($this->content ?? ''), 157);
    }

    public function effectiveUpvotes(): int
    {
        return max(0, $this->upvotes + $this->shadow_votes);
    }

    /**
     * Punteggio della vetrina: logaritmo dei voti più una spinta temporale.
     * Il tetto sui voti impedisce a un contenuto votato in massa di occupare
     * la prima posizione per sempre, mentre la costante di 3 giorni definisce
     * quanto conta l'anzianità rispetto al gradimento.
     */
    public function computeScore(): float
    {
        if ($this->first_published_at === null) {
            return 0.0;
        }
        $votes = max(1, min($this->effectiveUpvotes(), 60));
        $timestamp = Dates::parse($this->first_published_at)?->getTimestamp() ?? 0;
        // Epoca di riferimento: 1 gennaio 2020.
        return log10($votes) + ($timestamp - 1577836800) / (3 * 86400);
    }

    public function recalculateUpvotes(): void
    {
        $this->upvotes = (int) self::db()->fetchColumn(
            'SELECT COUNT(*) FROM {{upvotes}} WHERE post_id = ? AND marked = 0',
            [$this->id]
        );
        $this->updateRow([
            'upvotes' => $this->upvotes,
            'score'   => $this->computeScore(),
        ]);
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
