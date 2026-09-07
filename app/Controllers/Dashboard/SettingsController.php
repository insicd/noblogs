<?php

declare(strict_types=1);

namespace Noblogs\Controllers\Dashboard;

use Noblogs\Core\Config;
use Noblogs\Core\I18n;
use Noblogs\Core\Response;
use Noblogs\Core\Url;
use Noblogs\Models\Blog;
use Noblogs\Models\Redirect;
use Noblogs\Support\Dates;
use Noblogs\Support\Str;

/**
 * Impostazioni di un blog: generali, avanzate, dominio, redirect, e la
 * cancellazione definitiva.
 */
final class SettingsController extends DashboardController
{
    /**
     * Percorsi che il sito serve già: se l'elenco degli articoli finisse su
     * uno di questi, la rotta di sistema avrebbe la precedenza e la pagina
     * dell'autore sarebbe irraggiungibile.
     */
    private const RESERVED_PATHS = [
        'cerca', 'feed', 'rss', 'atom', 'sitemap.xml', 'robots.txt',
        'iscriviti', 'conferma-iscrizione', 'disiscriviti', 'hit', 'upvote',
        'upvote-info',
    ];

    public function general(string $blog): Response
    {
        $found = $this->openBlog($blog);
        if ($found instanceof Response) {
            return $found;
        }

        $return = $this->blogUrl($found, '/impostazioni');

        if ($this->request->isPost()) {
            $csrf = $this->requireCsrf();
            if ($csrf !== null) {
                return $csrf;
            }

            $title = $this->request->trimmed('title');
            if ($title === '') {
                return $this->withInput($return, $this->request->post, __('blog.create.title_required'));
            }

            $favicon = $this->request->trimmed('favicon');
            if ($favicon !== '' && !$this->isValidFavicon($favicon)) {
                return $this->withInput($return, $this->request->post, __('settings.error.favicon'));
            }

            $lang = mb_strtolower($this->request->trimmed('lang'));
            if (!preg_match('/^[a-z]{2}(-[a-z]{2})?$/', $lang)) {
                return $this->withInput($return, $this->request->post, __('settings.error.lang'));
            }

            $path = Str::slug($this->request->trimmed('blog_path'));
            if ($path === '') {
                $path = 'blog';
            }
            if (in_array($path, self::RESERVED_PATHS, true)) {
                return $this->withInput($return, $this->request->post, __('settings.error.blog_path_reserved'));
            }

            $found->update([
                'title'                => mb_substr($title, 0, 200),
                'meta_description'     => mb_substr($this->request->trimmed('meta_description'), 0, 300) ?: null,
                'meta_image'           => mb_substr($this->request->trimmed('meta_image'), 0, 300) ?: null,
                'favicon'              => $favicon !== '' ? mb_substr($favicon, 0, 300) : '🌐',
                'lang'                 => $lang,
                'date_format'          => mb_substr($this->request->trimmed('date_format'), 0, 32) ?: 'j M Y',
                'blog_path'            => mb_substr($path, 0, 100),
                'analytics_active'     => $this->request->boolean('analytics_active'),
                'upvotes_active'       => $this->request->boolean('upvotes_active'),
                'subscriptions_active' => $this->request->boolean('subscriptions_active'),
                'discoverable'         => $this->request->boolean('discoverable'),
            ]);

            return $this->succeed($return, __('settings.saved'));
        }

        return $this->panel('dashboard/settings-general', [
            'pageTitle' => __('settings.general_title'),
            'section'   => 'settings',
            'blog'      => $found,
            'locales'   => I18n::available(),
            'formats'   => $this->dateFormats($found),
        ]);
    }

    public function advanced(string $blog): Response
    {
        $found = $this->openBlog($blog);
        if ($found instanceof Response) {
            return $found;
        }

        $return = $this->blogUrl($found, '/impostazioni/avanzate');

        if ($this->request->isPost()) {
            $csrf = $this->requireCsrf();
            if ($csrf !== null) {
                return $csrf;
            }

            $alias = Str::slug($this->request->trimmed('rss_alias'), '-', true);
            $payload = [
                'robots_txt'    => mb_substr($this->request->input('robots_txt', '') ?? '', 0, 4000) ?: null,
                'rss_alias'     => $alias !== '' ? mb_substr($alias, 0, 100) : null,
                'post_template' => mb_substr($this->request->input('post_template', '') ?? '', 0, 20000) ?: null,
            ];

            // Il codice nell'intestazione e nel piè di pagina finisce nella
            // pagina senza bonifica: si accetta solo dai blog a cui
            // l'amministrazione ha concesso l'HTML libero.
            if ($found->allow_raw_html) {
                $payload['header_directive'] = mb_substr($this->request->input('header_directive', '') ?? '', 0, 20000) ?: null;
                $payload['footer_directive'] = mb_substr($this->request->input('footer_directive', '') ?? '', 0, 20000) ?: null;
            }

            $found->update($payload);

            return $this->succeed($return, __('settings.saved'));
        }

        return $this->panel('dashboard/settings-advanced', [
            'pageTitle'    => __('settings.advanced_title'),
            'section'      => 'settings',
            'blog'         => $found,
            'contactEmail' => (string) Config::get('site.contact_email', ''),
        ]);
    }

    public function domain(string $blog): Response
    {
        $found = $this->openBlog($blog);
        if ($found instanceof Response) {
            return $found;
        }

        $enabled = (bool) Config::get('routing.custom_domains', true);
        $return = $this->blogUrl($found, '/impostazioni/dominio');

        if ($this->request->isPost()) {
            if (!$enabled) {
                return $this->forbidden(__('settings.domain_disabled'));
            }

            $csrf = $this->requireCsrf();
            if ($csrf !== null) {
                return $csrf;
            }

            $domain = mb_strtolower($this->request->trimmed('domain'));
            $domain = (string) preg_replace('#^https?://#', '', $domain);
            $domain = rtrim(explode('/', $domain)[0], '.');

            if ($domain === '') {
                $found->update(['domain' => null]);
                return $this->succeed($return, __('settings.domain_removed'));
            }

            $error = $this->validateDomain($domain, $found);
            if ($error !== null) {
                return $this->withInput($return, $this->request->post, $error);
            }

            $found->update(['domain' => mb_substr($domain, 0, 191)]);

            return $this->succeed($return, __('settings.domain_saved'));
        }

        return $this->panel('dashboard/settings-domain', [
            'pageTitle'  => __('settings.domain_title'),
            'section'    => 'settings',
            'blog'       => $found,
            'enabled'    => $enabled,
            'target'     => $found->subdomain . '.' . Url::mainDomain(),
            'mainDomain' => Url::mainDomain(),
        ]);
    }

    public function redirects(string $blog): Response
    {
        $found = $this->openBlog($blog);
        if ($found instanceof Response) {
            return $found;
        }

        $return = $this->blogUrl($found, '/impostazioni/redirect');

        if ($this->request->isPost()) {
            $csrf = $this->requireCsrf();
            if ($csrf !== null) {
                return $csrf;
            }

            $count = Redirect::replaceAll($found, $this->request->input('redirects', '') ?? '');

            return $this->succeed($return, __('settings.redirects_saved', ['count' => $count]));
        }

        return $this->panel('dashboard/settings-redirects', [
            'pageTitle' => __('settings.redirects_title'),
            'section'   => 'settings',
            'blog'      => $found,
            'text'      => Redirect::asText($found),
        ]);
    }

    public function destroy(string $blog): Response
    {
        $found = $this->openBlog($blog);
        if ($found instanceof Response) {
            return $found;
        }

        $csrf = $this->requireCsrf();
        if ($csrf !== null) {
            return $csrf;
        }

        // La conferma è digitare il sottodominio: un clic distratto non deve
        // poter cancellare anni di scrittura.
        if (mb_strtolower($this->request->trimmed('confirm')) !== $found->subdomain) {
            $this->flash('error', __('settings.delete_mismatch'));
            return $this->redirect($this->blogUrl($found, '/impostazioni'));
        }

        $subdomain = $found->subdomain;
        $this->deleteBlogCompletely($found);

        return $this->succeed(Url::to('/dashboard'), __('settings.deleted', ['blog' => $subdomain]));
    }

    // -----------------------------------------------------------------------
    // Interno
    // -----------------------------------------------------------------------

    private function isValidFavicon(string $value): bool
    {
        return Str::isSingleGlyph($value)
            || Str::isHttpUrl($value)
            || str_starts_with($value, '/');
    }

    private function validateDomain(string $domain, Blog $blog): ?string
    {
        if (!preg_match('/^(?=.{4,191}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', $domain)) {
            return __('settings.error.domain_format');
        }

        $main = Url::mainDomain();
        if ($domain === $main || str_ends_with($domain, '.' . $main)) {
            return __('settings.error.domain_is_platform', ['domain' => $main]);
        }

        if (Blog::domainTaken($domain, $blog->id) || Blog::domainTaken('www.' . $domain, $blog->id)) {
            return __('settings.error.domain_taken');
        }

        return null;
    }

    /**
     * Formati di data proposti, ognuno con il suo esempio già reso nella
     * lingua del blog: è l'unico modo per far capire cosa si sta scegliendo.
     *
     * @return array<string,string>
     */
    private function dateFormats(Blog $blog): array
    {
        $sample = Dates::parse('2026-03-05 09:30:00') ?? Dates::now();
        $formats = ['j M Y', 'j F Y', 'd/m/Y', 'Y-m-d', 'l j F Y', 'M j, Y'];

        $examples = [];
        foreach ($formats as $format) {
            $examples[$format] = Dates::format($sample, $format, $blog->locale());
        }
        return $examples;
    }
}
