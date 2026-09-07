<?php

declare(strict_types=1);

/**
 * Strings for the public platform pages: landing page, showcase, information
 * pages and error pages.
 *
 * The long prose of the about, privacy, terms, help and Markdown pages lives
 * in the views themselves, in Italian: it is prose, not labels.
 */
return [
    // -----------------------------------------------------------------------
    // Shared shell
    // -----------------------------------------------------------------------
    'platform.nav.label'      => 'Platform navigation',
    'platform.nav.discover'   => 'Discover',
    'platform.nav.help'       => 'Help',
    'platform.nav.about'      => 'About',
    'platform.nav.login'      => 'Sign in',
    'platform.nav.register'   => 'Sign up',
    'platform.nav.dashboard'  => 'Dashboard',
    'platform.skip'           => 'Skip to content',
    'platform.flash.label'    => 'Messages',

    'platform.footer.privacy'     => 'Privacy',
    'platform.footer.terms'       => 'Terms',
    'platform.footer.help'        => 'Help',
    'platform.footer.source'      => 'Source code',
    'platform.footer.no_tracking' => 'No tracking, no profiling cookies, no third-party services.',

    // -----------------------------------------------------------------------
    // Landing page
    // -----------------------------------------------------------------------
    'platform.home.title'          => 'Light blogs, no tracking',
    'platform.home.heading'        => 'Just write.',
    'platform.home.lead'           => 'Noblogs is a free blogging service: no ads, no tracking, no paid plans. You write in Markdown, and the page that comes out weighs a few kilobytes and reads well on any device.',
    'platform.home.form_heading'   => 'Start your blog',
    'platform.home.form_note'      => 'All you need is an email address and thirty seconds.',
    'platform.home.form_submit'    => 'Get started',
    'platform.home.stats_blogs'    => 'active blogs',
    'platform.home.stats_posts'    => 'published posts',
    'platform.home.recent_heading' => 'From the showcase',
    'platform.home.recent_more'    => 'Browse every post',
    'platform.home.features_heading' => 'How it works',

    'platform.home.feature.privacy.title' => 'Statistics without spying',
    'platform.home.feature.privacy.body'  => 'Reads are counted with a non-reversible daily fingerprint: no cookies, no stored IP addresses, no profiles. Blog readers do not even get a cookie.',
    'platform.home.feature.speed.title'   => 'Light pages',
    'platform.home.feature.speed.body'    => 'HTML and one stylesheet. No framework, no external fonts, no third-party scripts: pages open even on a slow connection.',
    'platform.home.feature.markdown.title' => 'Markdown, not forms',
    'platform.home.feature.markdown.body'  => 'Write in Markdown, with tables, footnotes, code blocks and directives that drop post lists wherever you need them.',
    'platform.home.feature.yours.title'   => 'Your content stays yours',
    'platform.home.feature.yours.body'    => 'Export everything as Markdown files whenever you want, point your own domain at the blog, and delete the account and its content at any time.',

    // -----------------------------------------------------------------------
    // Showcase
    // -----------------------------------------------------------------------
    'discover.title'          => 'Discover',
    'discover.heading'        => 'Discover',
    'discover.intro'          => 'Posts from the blogs hosted here, chosen by the people who write them. Only blogs approved by moderation show up.',
    'discover.filters_label'  => 'Showcase filters',
    'discover.order_label'    => 'Sort by',
    'discover.lang_label'     => 'Language',
    'discover.order.score'    => 'Featured',
    'discover.order.recent'   => 'Most recent',
    'discover.order.random'   => 'Random',
    'discover.lang_label'     => 'Language',
    'discover.lang_all'       => 'All',
    'discover.filter_submit'  => 'Apply',
    'discover.empty'          => 'There is nothing to show here yet.',
    'discover.feed'           => 'Showcase feed',
    'discover.random'         => 'Take me to a random post',
    'discover.random_blog'    => 'Take me to a random blog',
    'discover.upvotes'        => ':count upvotes',
    'discover.on_blog'        => 'on :blog',
    'discover.pagination'     => 'Showcase pagination',
    'discover.newer'          => 'Previous page',
    'discover.older'          => 'Next page',
    'discover.page_of'        => 'Page :current of :total',

    'discover.search_title'       => 'Search the showcase',
    'discover.search_label'       => 'Search posts and blogs',
    'discover.search_placeholder' => 'Title, tag or blog name',
    'discover.search_button'      => 'Search',
    'discover.search_hint'        => 'The search looks at the post title, its tags, and the address and title of the blog. Every word must appear.',
    'discover.search_results'     => 'Results for “:query”',
    'discover.search_count'       => ':count results',
    'discover.search_empty'       => 'No results for “:query”.',

    // -----------------------------------------------------------------------
    // Information page titles
    // -----------------------------------------------------------------------
    'platform.about.title'    => 'About',
    'platform.privacy.title'  => 'Privacy notice',
    'platform.terms.title'    => 'Terms of use',
    'platform.help.title'     => 'Help',
    'platform.markdown.title' => 'Markdown guide',

    'platform.markdown.source'     => 'What you write',
    'platform.markdown.result'     => 'What it looks like',
    'platform.markdown.contents'   => 'On this page',
    'platform.markdown.directive_code' => 'Directive',
    'platform.markdown.directive_aliases' => 'Aliases',
    'platform.markdown.directive_effect' => 'What it inserts',

    // -----------------------------------------------------------------------
    // Error pages that use the platform shell
    // -----------------------------------------------------------------------
    'errors.404.title'    => 'Page not found',
    'errors.404.body'     => 'This address does not match any page. It may have been mistyped, or the page may be gone.',
    'errors.404.links'    => 'You can start again from here:',
    'errors.403.title'    => 'Access denied',
    'errors.403.body'     => 'You do not have permission to see this page. If you think this is a mistake, try signing out and in again.',
    'errors.home'         => 'Home page',
];
