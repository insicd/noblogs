<?php

declare(strict_types=1);

/**
 * Administration area strings.
 *
 * Keys are prefixed with `admin.`: per-area catalogues get merged into a single
 * flat array, and the prefix keeps them from colliding.
 */
return [
    // Header and navigation
    'admin.area'                   => 'Administration',
    'admin.area_short'             => 'Admin',
    'admin.nav.label'              => 'Administration sections',
    'admin.nav.dashboard'          => 'Overview',
    'admin.nav.blogs'              => 'Blogs',
    'admin.nav.users'              => 'Users',
    'admin.nav.log'                => 'Log',
    'admin.nav.settings'           => 'Settings',
    'admin.nav.back_to_dashboard'  => 'My dashboard',
    'admin.nav.logout'             => 'Sign out',
    'admin.footer'                 => 'Noblogs :version — restricted to moderators and administrators.',

    'admin.role.admin'     => 'Administrator',
    'admin.role.moderator' => 'Moderator',
    'admin.role.user'      => 'User',

    'admin.notice.title' => 'Platform notice',

    'admin.note.label'       => 'Moderator note',
    'admin.note.placeholder' => 'Note (optional)',

    'admin.filters.apply' => 'Apply',

    'admin.pagination.label'    => 'Pagination',
    'admin.pagination.previous' => 'Previous',
    'admin.pagination.next'     => 'Next',
    'admin.pagination.position' => 'Page :current of :total',

    'admin.error.unknown_action' => 'Unknown action.',

    // ---------------------------------------------------------------------
    // Overview
    // ---------------------------------------------------------------------
    'admin.dashboard.title' => 'Overview',

    'admin.dashboard.stat.blogs'   => 'Blogs',
    'admin.dashboard.stat.pending' => 'Awaiting review',
    'admin.dashboard.stat.users'   => 'Users',
    'admin.dashboard.stat.posts'   => 'Posts',
    'admin.dashboard.stat.reads'   => 'Reads (30 days)',
    'admin.dashboard.stat.storage' => 'Storage used',
    'admin.dashboard.secondary'    => 'Hidden: :hidden · Flagged: :flagged · Moderators and administrators: :staff',

    'admin.dashboard.queue.title'      => 'Moderation queue',
    'admin.dashboard.queue.intro'      => 'Blogs waiting for a decision, riskiest first. The excerpt is there so you can decide without opening each one.',
    'admin.dashboard.queue.empty'      => 'Nothing waiting: the queue is empty.',
    'admin.dashboard.queue.owner'      => 'by :email',
    'admin.dashboard.queue.created'    => 'created :since ago',
    'admin.dashboard.queue.posts'      => ':count posts',
    'admin.dashboard.queue.posts_one'  => '1 post',
    'admin.dashboard.queue.score'      => 'risk :score',
    'admin.dashboard.queue.no_content' => 'The home page is empty.',

    'admin.dashboard.recent_log' => 'Latest decisions',
    'admin.dashboard.full_log'   => 'See the full log',

    // ---------------------------------------------------------------------
    // Maintenance
    // ---------------------------------------------------------------------
    'admin.maintenance.title'     => 'Maintenance',
    'admin.maintenance.intro'     => 'Removes analytics past the retention window, never-confirmed subscriptions and expired cache entries; re-syncs themes and recomputes blog counters.',
    'admin.maintenance.cron_hint' => 'The same work can be scheduled with «bin/noblogs manutenzione»: if you have cron access, prefer that.',
    'admin.maintenance.run'       => 'Run maintenance',
    'admin.maintenance.done'      => 'Maintenance done: :hits reads removed, :subscribers stale subscriptions, :cache cache entries, :themes themes synced, :blogs blogs recomputed.',

    // ---------------------------------------------------------------------
    // Blogs
    // ---------------------------------------------------------------------
    'admin.blogs.title'              => 'Blogs',
    'admin.blogs.search_label'       => 'Search',
    'admin.blogs.search_placeholder' => 'Address, title, domain or author email',
    'admin.blogs.filter_label'       => 'State',
    'admin.blogs.sort_label'         => 'Sort by',
    'admin.blogs.count'              => ':count blogs found.',
    'admin.blogs.count_one'          => 'One blog found.',
    'admin.blogs.empty'              => 'No blog matches these filters.',

    'admin.blogs.filter.all'      => 'All',
    'admin.blogs.filter.pending'  => 'Awaiting review',
    'admin.blogs.filter.approved' => 'Approved',
    'admin.blogs.filter.hidden'   => 'Hidden',
    'admin.blogs.filter.flagged'  => 'Flagged',

    'admin.blogs.sort.recent'   => 'Newest',
    'admin.blogs.sort.activity' => 'Last published',
    'admin.blogs.sort.risk'     => 'Risk score',
    'admin.blogs.sort.storage'  => 'Storage used',
    'admin.blogs.sort.alpha'    => 'Address',

    'admin.blogs.col.blog'    => 'Blog',
    'admin.blogs.col.owner'   => 'Author',
    'admin.blogs.col.state'   => 'State',
    'admin.blogs.col.posts'   => 'Posts',
    'admin.blogs.col.storage' => 'Storage',
    'admin.blogs.col.created' => 'Created',

    'admin.blogs.state.pending'  => 'Awaiting review',
    'admin.blogs.state.approved' => 'Approved',
    'admin.blogs.state.hidden'   => 'Hidden',
    'admin.blogs.state.flagged'  => 'Flagged',
    'admin.blogs.state.raw_html' => 'Raw HTML',

    'admin.blogs.action.approva'              => 'Approve',
    'admin.blogs.action.nascondi'             => 'Hide',
    'admin.blogs.action.mostra'               => 'Unhide',
    'admin.blogs.action.segnala'              => 'Flag',
    'admin.blogs.action.rimuovi_segnalazione' => 'Remove flag',
    'admin.blogs.action.consenti_html'        => 'Allow HTML',
    'admin.blogs.action.revoca_html'          => 'Revoke HTML',
    'admin.blogs.action.elimina'              => 'Delete',

    'admin.blogs.done.approva'              => 'Blog :blog approved.',
    'admin.blogs.done.nascondi'             => 'Blog :blog hidden: it is no longer reachable by the public.',
    'admin.blogs.done.mostra'               => 'Blog :blog is visible again.',
    'admin.blogs.done.segnala'              => 'Blog :blog flagged and put back in the review queue.',
    'admin.blogs.done.rimuovi_segnalazione' => 'Flag removed from blog :blog.',
    'admin.blogs.done.consenti_html'        => 'Blog :blog can now use raw HTML in its content.',
    'admin.blogs.done.revoca_html'          => 'Raw HTML revoked for blog :blog.',
    'admin.blogs.done.generic'              => 'Blog :blog updated.',

    'admin.blogs.error.not_found' => 'This blog no longer exists.',

    'admin.blogs.delete.title'            => 'Delete :blog',
    'admin.blogs.delete.heading'          => 'You are about to delete «:blog»',
    'admin.blogs.delete.warning'          => 'This cannot be undone. Content, uploaded files, analytics and subscribers all go away.',
    'admin.blogs.delete.item.address'     => 'Address',
    'admin.blogs.delete.item.owner'       => 'Author',
    'admin.blogs.delete.item.posts'       => 'Posts',
    'admin.blogs.delete.item.pages'       => 'Pages',
    'admin.blogs.delete.item.files'       => 'Uploaded files',
    'admin.blogs.delete.item.storage'     => 'Storage used',
    'admin.blogs.delete.item.subscribers' => 'Subscribers',
    'admin.blogs.delete.item.reads'       => 'Recorded reads',
    'admin.blogs.delete.files_note'       => 'The folder :path will be removed with everything inside it.',
    'admin.blogs.delete.alternative'      => 'If the problem is temporary, «Hide» takes the blog off the public web while leaving the content intact: that is almost always the right call.',
    'admin.blogs.delete.type_subdomain'   => 'To confirm, type the blog address here: :blog',
    'admin.blogs.delete.confirm_button'   => 'Delete permanently',
    'admin.blogs.delete.mismatch'         => 'The address you typed does not match: the blog was not deleted.',
    'admin.blogs.delete.done'             => 'Blog :blog deleted, with all its content and files.',

    // ---------------------------------------------------------------------
    // Users
    // ---------------------------------------------------------------------
    'admin.users.title'              => 'Users',
    'admin.users.search_label'       => 'Search by email',
    'admin.users.search_placeholder' => 'part of the email address',
    'admin.users.filter_label'       => 'State',
    'admin.users.sort_label'         => 'Sort by',
    'admin.users.count'              => ':count users found.',
    'admin.users.count_one'          => 'One user found.',
    'admin.users.empty'              => 'No user matches these filters.',
    'admin.users.you'                => 'this is you',
    'admin.users.never'              => 'never',
    'admin.users.moderator_scope'    => 'As a moderator you can suspend, reinstate and verify accounts. Roles and deletions are for administrators.',

    'admin.users.filter.all'        => 'All',
    'admin.users.filter.active'     => 'Active',
    'admin.users.filter.suspended'  => 'Suspended',
    'admin.users.filter.unverified' => 'Email not verified',
    'admin.users.filter.staff'      => 'Moderators and administrators',

    'admin.users.sort.recent' => 'Newest',
    'admin.users.sort.login'  => 'Last sign-in',
    'admin.users.sort.email'  => 'Email',
    'admin.users.sort.blogs'  => 'Number of blogs',

    'admin.users.col.email'      => 'Email',
    'admin.users.col.role'       => 'Role',
    'admin.users.col.blogs'      => 'Blogs / limit',
    'admin.users.col.state'      => 'State',
    'admin.users.col.registered' => 'Registered',
    'admin.users.col.last_login' => 'Last sign-in',

    'admin.users.state.active'     => 'Active',
    'admin.users.state.suspended'  => 'Suspended',
    'admin.users.state.unverified' => 'Email not verified',

    'admin.users.limit_label' => 'Blog limit',

    'admin.users.action.sospendi'            => 'Suspend',
    'admin.users.action.riattiva'            => 'Reinstate',
    'admin.users.action.verifica_email'      => 'Mark email as verified',
    'admin.users.action.promuovi_moderatore' => 'Make moderator',
    'admin.users.action.promuovi_admin'      => 'Make administrator',
    'admin.users.action.revoca_ruolo'        => 'Back to plain user',
    'admin.users.action.cambia_limite'       => 'Change limit',
    'admin.users.action.elimina'             => 'Delete',

    'admin.users.done.sospendi'            => 'Account :email suspended: they cannot sign in and their blogs stay offline.',
    'admin.users.done.riattiva'            => 'Account :email reinstated.',
    'admin.users.done.verifica_email'      => 'Email of :email marked as verified.',
    'admin.users.done.promuovi_moderatore' => ':email is now a moderator.',
    'admin.users.done.promuovi_admin'      => ':email is now an administrator.',
    'admin.users.done.revoca_ruolo'        => ':email is a plain user again.',
    'admin.users.done.cambia_limite'       => 'Limit for :email set to :limit blogs.',
    'admin.users.done.generic'             => 'Account :email updated.',

    'admin.users.error.not_found'     => 'This account no longer exists.',
    'admin.users.error.self_role'     => 'You cannot change your own role: ask another administrator.',
    'admin.users.error.self_account'  => 'You cannot suspend or delete your own account from here.',
    'admin.users.error.last_admin'    => 'This is the last active administrator: appoint another one first.',
    'admin.users.error.invalid_limit' => 'The blog limit must be a number between 0 and 1000.',

    'admin.users.delete.title'            => 'Delete :email',
    'admin.users.delete.heading'          => 'You are about to delete the account :email',
    'admin.users.delete.warning'          => 'This cannot be undone. Along with the account go all its blogs, with posts, files, analytics and subscribers.',
    'admin.users.delete.item.blogs'       => 'Blogs',
    'admin.users.delete.item.posts'       => 'Posts and pages',
    'admin.users.delete.item.files'       => 'Uploaded files',
    'admin.users.delete.item.storage'     => 'Storage used',
    'admin.users.delete.item.subscribers' => 'Subscribers',
    'admin.users.delete.blogs_list'       => 'Blogs that will be deleted:',
    'admin.users.delete.alternative'      => 'Suspending the account looks the same from the outside and can be undone: consider it first.',
    'admin.users.delete.type_email'       => 'To confirm, type the email address here: :email',
    'admin.users.delete.confirm_button'   => 'Delete permanently',
    'admin.users.delete.mismatch'         => 'The address you typed does not match: the account was not deleted.',
    'admin.users.delete.done'             => 'Account :email deleted, with all its blogs.',

    // ---------------------------------------------------------------------
    // Settings
    // ---------------------------------------------------------------------
    'admin.settings.title' => 'Platform settings',
    'admin.settings.intro' => 'These values change on the fly and take precedence over config/config.php. The settings that could lock you out — database, domain, routing, keys — stay in the file only.',

    'admin.settings.section.identity' => 'Identity',
    'admin.settings.section.access'   => 'Access and review',
    'admin.settings.section.limits'   => 'Default limits',
    'admin.settings.section.notice'   => 'Global notice',

    'admin.settings.site_name'          => 'Site name',
    'admin.settings.tagline'            => 'Tagline',
    'admin.settings.tagline_hint'       => 'One line under the name, on the landing page and in the metadata.',
    'admin.settings.contact_email'      => 'Contact email',
    'admin.settings.contact_email_hint' => 'Shown on public pages to whoever needs to report abuse. Leave empty not to publish it.',

    'admin.settings.registration_open'      => 'Registrations open',
    'admin.settings.registration_open_hint' => 'Unchecked, the sign-up form stays reachable but refuses new accounts. Existing accounts are untouched.',
    'admin.settings.verify_email'           => 'Require email verification',
    'admin.settings.verify_email_hint'      => 'Without verification no blog can be created. It is the first barrier against automated sign-ups.',
    'admin.settings.review_blogs'           => 'Require review of new blogs',
    'admin.settings.review_blogs_hint'      => 'New blogs stay out of search engines and out of the showcase until a moderator approves them. They remain readable to anyone with the address (via the path on the main domain, until the third-level host is created).',

    'admin.settings.blogs_per_user'      => 'Blogs per user',
    'admin.settings.blogs_per_user_hint' => 'Applies to new accounts; a single user\'s limit is changed from the Users page.',
    'admin.settings.posts_per_blog'      => 'Posts per blog',
    'admin.settings.storage_per_blog'    => 'Storage per blog (MB)',
    'admin.settings.upload_max'          => 'Maximum size of a single file (MB)',
    'admin.settings.upload_max_hint'     => 'The server has limits of its own (upload_max_filesize and post_max_size): this value cannot exceed them.',

    'admin.settings.notice'      => 'Notice message',
    'admin.settings.notice_hint' => 'Shown at the top of the administration area and of the user dashboard. Leave empty to show nothing.',

    'admin.settings.saved'     => 'Settings saved.',
    'admin.settings.log_note'  => 'Platform settings updated.',
    'admin.settings.file_note' => 'To clear a value and fall back to config/config.php, empty the field and save.',

    'admin.settings.error.name_required'     => 'The site name cannot be empty.',
    'admin.settings.error.contact_email'     => 'The contact email does not look like a valid address.',
    'admin.settings.error.limits'            => 'One of the limits is outside the allowed range.',
    'admin.settings.error.upload_over_quota' => 'The per-file limit cannot exceed the total storage per blog.',

    // ---------------------------------------------------------------------
    // Log
    // ---------------------------------------------------------------------
    'admin.log.title'      => 'Moderation log',
    'admin.log.intro'      => 'Who decided what, and when. Entries cannot be deleted from the panel.',
    'admin.log.empty'      => 'The log is empty.',
    'admin.log.system'     => 'system',
    'admin.log.col.when'   => 'When',
    'admin.log.col.actor'  => 'Who',
    'admin.log.col.action' => 'Action',
    'admin.log.col.blog'   => 'Blog',
    'admin.log.col.note'   => 'Note',
];
