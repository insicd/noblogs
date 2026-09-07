<?php

declare(strict_types=1);

namespace Noblogs\Controllers\Dashboard;

use Noblogs\Core\Response;
use Noblogs\Markdown\Renderer;
use Noblogs\Models\Theme;

/**
 * Aspetto del blog: galleria dei temi, CSS personale e barra di navigazione.
 */
final class ThemeController extends DashboardController
{
    public function edit(string $blog): Response
    {
        $found = $this->openBlog($blog);
        if ($found instanceof Response) {
            return $found;
        }

        $return = $this->blogUrl($found, '/aspetto');

        if ($this->request->isPost()) {
            $csrf = $this->requireCsrf();
            if ($csrf !== null) {
                return $csrf;
            }

            $slug = $this->request->trimmed('theme');
            if (!Theme::exists($slug)) {
                return $this->withInput($return, $this->request->post, __('theme.error.unknown'));
            }

            $css = $this->request->input('custom_css', '') ?? '';
            if (mb_strlen($css) > 200000) {
                return $this->withInput($return, $this->request->post, __('theme.error.css_too_long'));
            }

            $found->update([
                'theme'            => $slug,
                // Theme::sanitize taglia un eventuale </style>: senza, il CSS
                // potrebbe chiudere il blocco e iniettare markup nella pagina.
                'custom_css'       => Theme::sanitize($css) ?: null,
                'overwrite_styles' => $this->request->boolean('overwrite_styles'),
            ]);

            return $this->succeed($return, __('theme.saved'));
        }

        return $this->panel('dashboard/theme', [
            'pageTitle' => __('theme.title'),
            'section'   => 'theme',
            'blog'      => $found,
            'themes'    => Theme::all(),
            'sample'    => $this->sampleMarkup(),
        ]);
    }

    public function nav(string $blog): Response
    {
        $found = $this->openBlog($blog);
        if ($found instanceof Response) {
            return $found;
        }

        $return = $this->blogUrl($found, '/navigazione');

        if ($this->request->isPost()) {
            $csrf = $this->requireCsrf();
            if ($csrf !== null) {
                return $csrf;
            }

            $nav = $this->request->input('nav', '') ?? '';
            if (mb_strlen($nav) > 4000) {
                return $this->withInput($return, $this->request->post, __('nav.error.too_long'));
            }

            $found->update(['nav' => trim($nav) !== '' ? $nav : null]);

            return $this->succeed($return, __('nav.saved'));
        }

        return $this->panel('dashboard/nav', [
            'pageTitle'   => __('nav.title'),
            'section'     => 'nav',
            'blog'        => $found,
            'previewHtml' => Renderer::nav($found),
            'suggestion'  => \Noblogs\Models\Blog::defaultNav($found->displayLang()),
        ]);
    }

    /**
     * Markup dell'anteprima dei temi. Viene inserito in un iframe con srcdoc
     * assieme al CSS del tema: è l'unico modo di mostrare un tema per quello
     * che è, senza che i suoi stili invadano il pannello.
     */
    private function sampleMarkup(): string
    {
        return '<header class="site-header">'
            . '<h1 class="site-title"><a href="#">' . e(__('theme.sample_blog')) . '</a></h1>'
            . '<nav class="site-nav"><a href="#">' . e(__('theme.sample_home')) . '</a> '
            . '<a href="#">' . e(__('theme.sample_posts')) . '</a></nav>'
            . '</header><main><article class="post-full">'
            . '<h1 class="post-title">' . e(__('theme.sample_title')) . '</h1>'
            . '<p class="post-meta"><time>5 mar 2026</time></p>'
            . '<div class="post-content"><p>' . e(__('theme.sample_text')) . '</p>'
            . '<blockquote><p>' . e(__('theme.sample_quote')) . '</p></blockquote>'
            . '<ul class="post-list"><li><a href="#">' . e(__('theme.sample_link')) . '</a></li></ul>'
            . '</div></article></main>';
    }
}
