<?php

declare(strict_types=1);

namespace Noblogs\Models;

use Noblogs\Core\Config;
use Noblogs\Core\I18n;
use Noblogs\Core\Mailer;
use Noblogs\Core\Url;
use Noblogs\Support\Str;

/**
 * Un blog ospite: è il tenant della piattaforma.
 */
final class Blog extends Model
{
    protected static string $table = 'blogs';

    protected static array $casts = [
        'id'                    => 'int',
        'user_id'               => 'int',
        'overwrite_styles'      => 'bool',
        'analytics_active'      => 'bool',
        'upvotes_active'        => 'bool',
        'allow_raw_html'        => 'bool',
        'subscriptions_active'  => 'bool',
        'discoverable'          => 'bool',
        'reviewed'              => 'bool',
        'hidden'                => 'bool',
        'flagged'               => 'bool',
        'to_review'             => 'bool',
        'dodginess_score'       => 'float',
        'storage_used'          => 'int',
        'posts_last_12h'        => 'int',
    ];

    public int $user_id = 0;
    public string $subdomain = '';
    public ?string $domain = null;
    public string $title = '';
    public ?string $content = null;
    public ?string $nav = null;
    public ?string $meta_description = null;
    public ?string $meta_image = null;
    public string $favicon = '🌐';
    public string $lang = 'it';
    public string $blog_path = 'blog';
    public string $theme = 'default';
    public ?string $custom_css = null;
    public bool $overwrite_styles = false;
    public ?string $header_directive = null;
    public ?string $footer_directive = null;
    public string $date_format = 'j M Y';
    public ?string $post_template = null;
    public ?string $robots_txt = null;
    public ?string $rss_alias = null;
    public ?string $all_tags = null;
    public bool $analytics_active = true;
    public bool $upvotes_active = true;
    public bool $allow_raw_html = false;
    public bool $subscriptions_active = false;
    public bool $discoverable = true;
    public bool $reviewed = false;
    public bool $hidden = false;
    public bool $flagged = false;
    public bool $to_review = false;
    public float $dodginess_score = 0.0;
    public ?string $reviewer_note = null;
    public int $storage_used = 0;
    public int $posts_last_12h = 0;
    public string $created_at = '';
    public string $updated_at = '';
    public ?string $last_posted_at = null;

    /** @var array<string,self|null> Cache per richiesta della risoluzione tenant. */
    private static array $resolved = [];

    // -----------------------------------------------------------------------
    // Ricerca
    // -----------------------------------------------------------------------

    public static function findBySubdomain(string $subdomain): ?self
    {
        $subdomain = mb_strtolower(trim($subdomain));
        if ($subdomain === '' || !preg_match('/^[a-z0-9][a-z0-9-]{0,62}$/', $subdomain)) {
            return null;
        }
        if (array_key_exists('s:' . $subdomain, self::$resolved)) {
            return self::$resolved['s:' . $subdomain];
        }
        return self::$resolved['s:' . $subdomain] = self::hydrateOrNull(self::db()->fetch(
            'SELECT b.* FROM {{blogs}} b
             INNER JOIN {{users}} u ON u.id = b.user_id
             WHERE b.subdomain = ? AND u.is_active = 1',
            [$subdomain]
        ));
    }

    public static function findByDomain(string $domain): ?self
    {
        $domain = mb_strtolower(trim($domain));
        if ($domain === '') {
            return null;
        }
        if (array_key_exists('d:' . $domain, self::$resolved)) {
            return self::$resolved['d:' . $domain];
        }
        // Con e senza www puntano allo stesso blog, come si aspetta chiunque
        // configuri un dominio proprio.
        $bare = str_starts_with($domain, 'www.') ? substr($domain, 4) : $domain;
        return self::$resolved['d:' . $domain] = self::hydrateOrNull(self::db()->fetch(
            'SELECT b.* FROM {{blogs}} b
             INNER JOIN {{users}} u ON u.id = b.user_id
             WHERE (b.domain = ? OR b.domain = ?) AND u.is_active = 1',
            [$bare, 'www.' . $bare]
        ));
    }

    /** @return list<self> */
    public static function forUser(int $userId): array
    {
        return self::hydrateAll(self::db()->fetchAll(
            'SELECT * FROM {{blogs}} WHERE user_id = ? ORDER BY created_at ASC',
            [$userId]
        ));
    }

    public static function subdomainTaken(string $subdomain): bool
    {
        return self::db()->fetchColumn(
            'SELECT 1 FROM {{blogs}} WHERE subdomain = ?',
            [mb_strtolower($subdomain)]
        ) !== null;
    }

    public static function domainTaken(string $domain, int $exceptBlogId = 0): bool
    {
        return self::db()->fetchColumn(
            'SELECT 1 FROM {{blogs}} WHERE domain = ? AND id <> ?',
            [mb_strtolower($domain), $exceptBlogId]
        ) !== null;
    }

    /**
     * Un sottodominio è valido se rispetta le regole DNS, non è riservato e
     * non ricorda un'etichetta di sistema.
     */
    public static function validateSubdomain(string $subdomain): ?string
    {
        $subdomain = mb_strtolower(trim($subdomain));

        if ($subdomain === '') {
            return __('blog.error.subdomain_required');
        }
        if (mb_strlen($subdomain) < 3 || mb_strlen($subdomain) > 63) {
            return __('blog.error.subdomain_length');
        }
        if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', $subdomain)) {
            return __('blog.error.subdomain_format');
        }
        if (str_contains($subdomain, '--')) {
            return __('blog.error.subdomain_format');
        }
        $reserved = array_map('strval', (array) Config::get('security.reserved_subdomains', []));
        if (in_array($subdomain, $reserved, true)) {
            return __('blog.error.subdomain_reserved');
        }
        if (self::subdomainTaken($subdomain)) {
            return __('blog.error.subdomain_taken');
        }
        return null;
    }

    // -----------------------------------------------------------------------
    // Creazione e aggiornamento
    // -----------------------------------------------------------------------

    public static function create(User $user, string $subdomain, string $title, string $content = ''): self
    {
        $blog = new self();
        $blog->user_id = $user->id;
        $blog->subdomain = mb_strtolower(trim($subdomain));
        $blog->title = trim($title) !== '' ? trim($title) : $blog->subdomain;
        $blog->content = $content;
        $blog->lang = substr($user->locale, 0, 10);
        $blog->nav = self::defaultNav($blog->lang);
        $blog->robots_txt = "User-agent: *\nAllow: /";
        $blog->created_at = self::now();
        $blog->updated_at = $blog->created_at;
        // Con la revisione attiva i blog nuovi restano fuori dagli indici
        // finché un moderatore non li approva: è la difesa contro lo spam.
        $blog->reviewed = !Config::get('security.require_blog_review', true);

        $blog->insertRow([
            'user_id'    => $blog->user_id,
            'subdomain'  => $blog->subdomain,
            'title'      => $blog->title,
            'content'    => $blog->content,
            'nav'        => $blog->nav,
            'lang'       => $blog->lang,
            'favicon'    => $blog->favicon,
            'robots_txt' => $blog->robots_txt,
            'reviewed'   => $blog->reviewed ? 1 : 0,
            'created_at' => $blog->created_at,
            'updated_at' => $blog->updated_at,
        ]);

        $blog->refreshDodginess();

        return $blog;
    }

    /**
     * Avvisa il contatto dell'istanza che un blog nuovo è in coda di revisione.
     *
     * Si chiama dopo il commit: una SMTP lenta non deve tenere aperta la
     * transazione, e un fallimento dell'invio non deve far saltare la creazione.
     */
    public function notifyPendingReview(User $owner): void
    {
        if ($this->reviewed) {
            return;
        }

        $to = self::staffContactEmail();
        if ($to === '') {
            return;
        }

        $this->withSiteLocale(function () use ($to, $owner): void {
            $site = (string) Config::get('site.name', 'Noblogs');
            Mailer::send(
                $to,
                __('admin.review.email_subject', [
                    'title' => $this->title,
                    'site'  => $site,
                ]),
                __('admin.review.email_body', [
                    'site'      => $site,
                    'title'     => $this->title,
                    'email'     => $owner->email,
                    'path'      => Url::pathRoot($this),
                    'subdomain' => Url::subdomainRoot($this),
                    'blog_url'  => Url::blogRoot($this),
                    'admin_url' => Url::platform('/admin/blog') . '?stato=attesa&q=' . rawurlencode($this->subdomain),
                ]),
                $owner->email
            );
        });
    }

    /**
     * Conferma l'approvazione: al contatto dell'istanza il terzo livello appena
     * attivato, all'autore che il blog è raggiungibile anche da lì.
     */
    public function notifyApproved(?User $owner = null): void
    {
        $owner ??= $this->owner();
        $staff = self::staffContactEmail();
        $ownerEmail = $owner !== null && filter_var($owner->email, FILTER_VALIDATE_EMAIL) !== false
            ? $owner->email
            : '';

        $site = (string) Config::get('site.name', 'Noblogs');
        $path = Url::pathRoot($this);
        $subdomain = Url::subdomainRoot($this);

        if ($staff !== '' && strcasecmp($staff, $ownerEmail) !== 0) {
            $this->withSiteLocale(function () use ($staff, $owner, $ownerEmail, $site, $path, $subdomain): void {
                Mailer::send(
                    $staff,
                    __('admin.review.approved_email_subject', [
                        'title'     => $this->title,
                        'subdomain' => $subdomain,
                    ]),
                    __('admin.review.approved_email_body', [
                        'site'      => $site,
                        'title'     => $this->title,
                        'email'     => $ownerEmail,
                        'path'      => $path,
                        'subdomain' => $subdomain,
                    ]),
                    $owner?->email
                );
            });
        }

        if ($ownerEmail === '') {
            return;
        }

        $locale = I18n::isAvailable($owner->locale) ? $owner->locale : (string) Config::get('site.locale', 'it');
        $previous = I18n::locale();
        I18n::load($locale);
        try {
            Mailer::send(
                $ownerEmail,
                __('blog.approved.email_subject', [
                    'title'     => $this->title,
                    'site'      => $site,
                    'subdomain' => $subdomain,
                ]),
                __('blog.approved.email_body', [
                    'site'       => $site,
                    'title'      => $this->title,
                    'path'       => $path,
                    'subdomain'  => $subdomain,
                    'dashboard'  => Url::platform('/dashboard/' . $this->subdomain),
                ])
            );
        } finally {
            I18n::load($previous);
        }
    }

    private static function staffContactEmail(): string
    {
        $to = trim((string) (Setting::get('site.contact_email') ?: Config::get('site.contact_email', '')));
        if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            return '';
        }
        return $to;
    }

    /** @param callable():void $send */
    private function withSiteLocale(callable $send): void
    {
        $previous = I18n::locale();
        I18n::load((string) Config::get('site.locale', 'it'));
        try {
            $send();
        } finally {
            I18n::load($previous);
        }
    }

    /** @param array<string,mixed> $data */
    public function update(array $data): void
    {
        static $allowed = [
            'title', 'content', 'nav', 'meta_description', 'meta_image', 'favicon',
            'lang', 'blog_path', 'theme', 'custom_css', 'overwrite_styles',
            'header_directive', 'footer_directive', 'date_format', 'post_template',
            'robots_txt', 'rss_alias', 'domain', 'analytics_active', 'upvotes_active',
            'allow_raw_html', 'subscriptions_active', 'discoverable', 'reviewed', 'hidden', 'flagged',
            'to_review', 'dodginess_score', 'reviewer_note', 'storage_used',
            'all_tags', 'last_posted_at', 'posts_last_12h',
        ];

        $payload = array_intersect_key($data, array_flip($allowed));
        if ($payload === []) {
            return;
        }

        foreach ($payload as $key => $value) {
            if (!property_exists($this, $key)) {
                continue;
            }
            $this->$key = is_bool($this->$key) ? (bool) $value : $value;
        }

        // I booleani vanno scritti come interi.
        foreach ($payload as $key => $value) {
            if (is_bool($value)) {
                $payload[$key] = $value ? 1 : 0;
            }
        }

        $payload['updated_at'] = self::now();
        $this->updated_at = $payload['updated_at'];
        $this->updateRow($payload);

        unset(self::$resolved['s:' . $this->subdomain]);
        // Tema, CSS e contenuto della homepage sono tutti dentro l'HTML in
        // cache: qualunque modifica al blog la rende obsoleta.
        \Noblogs\Markdown\Cache::flushBlog($this->id);
    }

    // -----------------------------------------------------------------------
    // Stato
    // -----------------------------------------------------------------------

    /** Il blog può essere servito al pubblico? */
    public function isServable(): bool
    {
        return !$this->hidden;
    }

    /** Il blog può essere indicizzato dai motori di ricerca? */
    public function isIndexable(): bool
    {
        if ($this->hidden || $this->flagged) {
            return false;
        }
        return $this->reviewed || !Config::get('security.require_blog_review', true);
    }

    public function isDiscoverable(): bool
    {
        return $this->discoverable && $this->isIndexable();
    }

    public function owner(): ?User
    {
        return User::find($this->user_id);
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && ($user->id === $this->user_id || $user->isModerator());
    }

    // -----------------------------------------------------------------------
    // Contenuti
    // -----------------------------------------------------------------------

    /** @return list<string> */
    public function tags(): array
    {
        return self::decodeList($this->all_tags);
    }

    /** Ricalcola l'elenco dei tag usati dai post pubblicati. */
    public function refreshTags(): void
    {
        $rows = self::db()->fetchAll(
            'SELECT tags FROM {{posts}}
             WHERE blog_id = ? AND is_published = 1 AND hidden = 0 AND published_at <= UTC_TIMESTAMP()',
            [$this->id]
        );

        $tags = [];
        foreach ($rows as $row) {
            foreach (self::decodeList($row['tags'] ?? null) as $tag) {
                $tags[mb_strtolower($tag)] = $tag;
            }
        }
        ksort($tags);

        $this->all_tags = self::encodeList(array_values($tags));
        $this->updateRow(['all_tags' => $this->all_tags]);
    }

    /** Aggiorna i contatori usati dalla vetrina e dall'antiflood. */
    public function refreshCounters(): void
    {
        $row = self::db()->fetch(
            'SELECT MAX(published_at) AS last_posted,
                    SUM(published_at >= UTC_TIMESTAMP() - INTERVAL 12 HOUR) AS recent
             FROM {{posts}}
             WHERE blog_id = ? AND is_page = 0 AND is_published = 1 AND published_at <= UTC_TIMESTAMP()',
            [$this->id]
        );

        $this->last_posted_at = $row['last_posted'] ?? null;
        $this->posts_last_12h = (int) ($row['recent'] ?? 0);
        $this->updateRow([
            'last_posted_at' => $this->last_posted_at,
            'posts_last_12h' => $this->posts_last_12h,
        ]);

        $this->refreshDodginess();
    }

    /**
     * Punteggio di sospetto per la coda di revisione.
     *
     * Non è un classificatore: è un ordinamento. Homepage vuota, troppi
     * collegamenti, raffiche di pubblicazione e qualche parola tipica dello
     * spam alzano il numero, così il moderatore vede prima i casi più
     * chiari. Un punteggio alto non nasconde il blog da solo.
     */
    public function refreshDodginess(): void
    {
        $score = 0.0;
        $plain = trim((string) preg_replace('/\s+/u', ' ', strip_tags($this->content ?? '')));
        $haystack = mb_strtolower($this->title . "\n" . ($this->content ?? ''));

        if (mb_strlen($plain) < 80) {
            $score += 0.5;
        }

        $links = preg_match_all('/https?:\/\//i', $this->content ?? '') ?: 0;
        if ($links >= 4) {
            $score += min(1.2, 0.3 * $links);
        }

        if ($this->posts_last_12h >= 4) {
            $score += 0.6;
        }

        foreach (['casino', 'viagra', 'cialis', 'crypto', 'forex', 'loan', 'betting'] as $needle) {
            if (str_contains($haystack, $needle)) {
                $score += 0.7;
            }
        }

        $score = round(min(5.0, $score), 2);
        if (abs($score - $this->dodginess_score) < 0.001) {
            return;
        }

        $this->dodginess_score = $score;
        $this->updateRow(['dodginess_score' => $score]);
    }

    public function postCount(bool $includePages = false): int
    {
        $sql = 'SELECT COUNT(*) FROM {{posts}} WHERE blog_id = ?';
        if (!$includePages) {
            $sql .= ' AND is_page = 0';
        }
        return (int) self::db()->fetchColumn($sql, [$this->id]);
    }

    public function isAtPostLimit(): bool
    {
        return $this->postCount(true) >= (int) Config::get('limits.posts_per_blog', 5000);
    }

    // -----------------------------------------------------------------------
    // Presentazione
    // -----------------------------------------------------------------------

    public function faviconIsGlyph(): bool
    {
        return !Str::isHttpUrl($this->favicon) && !str_starts_with($this->favicon, '/');
    }

    public function description(): string
    {
        if ($this->meta_description !== null && trim($this->meta_description) !== '') {
            return $this->meta_description;
        }
        return Str::limit(Str::plain($this->content ?? ''), 157);
    }

    public function robotsTxt(): string
    {
        $base = trim($this->robots_txt ?? '');
        if ($base === '') {
            $base = "User-agent: *\nAllow: /";
        }
        if (!$this->isIndexable()) {
            return "User-agent: *\nDisallow: /\n";
        }
        // Gli endpoint di servizio non devono finire negli indici, qualunque
        // cosa scriva l'utente.
        return $base . "\n\nDisallow: /hit\nDisallow: /upvote\nDisallow: /subscribe\n";
    }

    public function displayLang(): string
    {
        return $this->lang !== '' ? $this->lang : (string) Config::get('site.locale', 'it');
    }

    public static function defaultNav(string $lang = 'it'): string
    {
        $home = $lang === 'it' ? 'Home' : 'Home';
        $blog = $lang === 'it' ? 'Blog' : 'Blog';
        return "[$home](/) [$blog](/blog/)";
    }

    /** Contenuto iniziale della homepage di un blog appena creato. */
    public static function starterContent(string $title, string $lang = 'it'): string
    {
        if (str_starts_with($lang, 'it')) {
            return "# $title\n\nBenvenuto. Questo è il tuo nuovo blog: sostituisci questo testo con "
                . "una presentazione, e comincia a scrivere.\n\n{{ posts|limit:5 }}\n";
        }
        return "# $title\n\nWelcome. This is your new blog: replace this text with an "
            . "introduction, then start writing.\n\n{{ posts|limit:5 }}\n";
    }

    public function locale(): string
    {
        $lang = substr($this->displayLang(), 0, 2);
        return I18n::isAvailable($lang) ? $lang : (string) Config::get('site.locale', 'it');
    }
}
