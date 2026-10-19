<?php

declare(strict_types=1);

namespace Noblogs\Federation;

use Noblogs\Core\Database;
use Noblogs\Models\Blog;

/**
 * Coppia RSA per blog: firma le attività in uscita e pubblica la chiave
 * nell'actor, così Mastodon e gli altri possono verificarle.
 */
final class Keys
{
    /** @return array{public:string,private:string}|null */
    public static function forBlog(Blog $blog): ?array
    {
        Schema::ensure();

        $row = Database::instance()->fetch(
            'SELECT public_key, private_key FROM {{ap_keys}} WHERE blog_id = ?',
            [$blog->id]
        );
        if ($row !== null) {
            return [
                'public'  => (string) $row['public_key'],
                'private' => (string) $row['private_key'],
            ];
        }

        $generated = self::generate();
        if ($generated === null) {
            return null;
        }

        Database::instance()->insert('ap_keys', [
            'blog_id'     => $blog->id,
            'public_key'  => $generated['public'],
            'private_key' => $generated['private'],
            'created_at'  => gmdate('Y-m-d H:i:s'),
        ]);

        return $generated;
    }

    /** @return array{public:string,private:string}|null */
    private static function generate(): ?array
    {
        if (!function_exists('openssl_pkey_new')) {
            return null;
        }

        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if ($resource === false) {
            return null;
        }

        $private = '';
        if (!openssl_pkey_export($resource, $private) || $private === '') {
            return null;
        }

        $details = openssl_pkey_get_details($resource);
        $public = is_array($details) ? (string) ($details['key'] ?? '') : '';
        if ($public === '') {
            return null;
        }

        return ['public' => $public, 'private' => $private];
    }
}
