<?php

declare(strict_types=1);

/**
 * Strings shared by the whole application.
 *
 * Keys are prefixed by area so catalogues split by file can be merged
 * without collisions.
 */
return [
    // General errors
    'error.not_found'          => 'Page not found.',
    'error.method_not_allowed' => 'Method not allowed.',
    'error.csrf'               => 'The session has expired. Please submit the form again.',
    'error.forbidden'          => 'You do not have access to this page.',
    'error.server'             => 'The server ran into an unexpected error.',
    'error.rate_limited'       => 'Too many attempts. Wait a few minutes and try again.',

    // Forms
    'form.leave_empty' => 'Leave this field empty',
    'form.save'        => 'Save',
    'form.cancel'      => 'Cancel',
    'form.delete'      => 'Delete',
    'form.confirm'     => 'Confirm',
    'form.required'    => 'required',
    'form.optional'    => 'optional',

    // Subdomain validation
    'blog.error.subdomain_required' => 'Choose an address for the blog.',
    'blog.error.subdomain_length'   => 'The address must be between 3 and 63 characters long.',
    'blog.error.subdomain_format'   => 'The address may only contain lowercase letters, numbers and single hyphens, and cannot start or end with a hyphen.',
    'blog.error.subdomain_reserved' => 'This address is reserved for the system. Choose another one.',
    'blog.error.subdomain_taken'    => 'This address is already taken.',

    // Markdown file headers
    'frontmatter.warning.unknown_key' => 'Unrecognised header key: :key',
    'frontmatter.warning.malformed'   => 'Header line without a colon: :line',

    // Uploaded files
    'media.error.too_large'        => 'The file exceeds the :size limit.',
    'media.error.too_large_php'    => 'The file exceeds the server upload limit.',
    'media.error.too_many_files'   => 'You have reached the maximum number of files for this blog.',
    'media.error.quota'            => 'You have used up the available space (:size).',
    'media.error.type_not_allowed' => 'Files with the .:ext extension are not allowed.',
    'media.error.type_mismatch'    => 'The file contents do not match its extension.',
    'media.error.directory'        => 'The destination folder could not be created.',
    'media.error.write'            => 'The file could not be saved on the server.',
    'media.error.partial'          => 'The upload stopped before it finished.',
    'media.error.no_file'          => 'No file selected.',
    'media.error.generic'          => 'Upload failed.',

    // Statistics
    'analytics.homepage' => 'Homepage',
];
