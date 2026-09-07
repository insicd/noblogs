<?php

declare(strict_types=1);

/**
 * Strings that appear on the public pages of a hosted blog.
 */
return [
    'site.nav_label'       => 'Site navigation',
    'site.not_found_title' => 'Page not found',
    'site.not_found_body'  => 'The page you were looking for does not exist, or it has been moved.',
    'site.back_home'       => 'Back to the homepage',
    'site.pending_review'  => 'Blog awaiting review',

    'blog.all_posts'     => 'All posts',
    'blog.no_posts'      => 'There is nothing to read yet.',
    'blog.posts_tagged'  => 'Posts tagged :tags',
    'blog.filtered_by'   => 'Filtered by :tags',
    'blog.remove_filter' => 'remove filter',
    'blog.pagination'    => 'Pagination',
    'blog.newer'         => 'Newer',
    'blog.older'         => 'Older',
    'blog.page_of'       => 'Page :current of :total',

    'post.toc'          => 'Contents',
    'post.next'         => 'Next post',
    'post.previous'     => 'Previous post',
    'post.upvote_label' => 'Mark that you liked this post',
    'post.draft_notice' => 'This is a draft: only you and people with the preview link can see it.',

    'search.title'        => 'Search',
    'search.label'        => 'Search posts',
    'search.placeholder'  => 'Words to search for',
    'search.button'       => 'Search',
    'search.results_for'  => 'Results for “:query”',
    'search.no_results'   => 'No results for “:query”.',
    'search.result_count' => ':count results',

    'subscribe.title'              => 'Subscribe to updates',
    'subscribe.intro'              => 'Leave your address to receive new posts from :blog by email.',
    'subscribe.label'              => 'Email address',
    'subscribe.placeholder'        => 'you@example.com',
    'subscribe.button'             => 'Subscribe',
    'subscribe.check_inbox'        => 'We sent you a message: open it and confirm the subscription. If it does not arrive within a few minutes, check your junk folder.',
    'subscribe.confirmed_title'    => 'Subscription confirmed',
    'subscribe.confirmed'          => 'Subscription confirmed: you will receive new posts from :blog.',
    'subscribe.unsubscribed_title' => 'Unsubscribed',
    'subscribe.unsubscribed'       => 'Your address has been removed. You will no longer receive messages from this blog.',
    'subscribe.invalid_email'      => 'This email address does not look valid.',
    'subscribe.invalid_link'       => 'The confirmation link is no longer valid.',
    'subscribe.back_to_blog'       => 'Back to the blog',
    'subscribe.privacy_note'       => 'Your address is only used to send you new posts. You can unsubscribe at any time from the link at the bottom of every message.',
    'subscribe.email_subject'      => 'Confirm your subscription to :blog',
    'subscribe.email_body'         => "Hello,\n\nsomeone asked to subscribe this address to updates from :blog.\n\nIf that was you, confirm here:\n:url\n\nIf that was not you, ignore this message: without confirmation you will receive nothing and the address will be deleted within a week.\n",
];
